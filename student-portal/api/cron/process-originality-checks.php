<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run every 15 minutes:
 *   php /home/yourusername/public_html/api/cron/process-originality-checks.php
 *
 * Drains `originality_check_queue` (see schema_student_portal.sql's comment
 * for why this table exists). For each pending project submission, asks the
 * AI Gateway to rate how similar it looks to a handful of other students'
 * submissions for the same assignment — a crude, real stand-in for the
 * originally-designed embedding-similarity pipeline, which needed cloud
 * infrastructure this build doesn't have. A second, complementary external
 * signal the original design called for — a MOSS-style public-code
 * plagiarism service, checked separately for literal copying from a public
 * repo — is **not implemented**: that's a distinct third-party SaaS
 * requiring its own account, not a generic "an API key, no infra" call
 * like the one accepted AI exception this build allows. Advisory only,
 * never an auto-reject gate (04e's explicit reasoning) — a low score is
 * just a flag a teacher sees on their grading queue.
 *
 * **Self-similarity is checked separately from other-similarity** (05d
 * §2's explicit reasoning) — "a student legitimately building on their own
 * earlier work" is the stated false-positive case a single undifferentiated
 * similarity number would punish. A high match against the *same*
 * student's own prior submissions is expected and never affects
 * `originality_score`; only the comparison against *other* students'
 * submissions to the same assignment is the actual plagiarism signal that
 * scores the column.
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\AiGateway;
use App\Core\AiUsageLog;
use App\Core\Database;

const BATCH_SIZE = 10;
const MAX_PEERS = 5;

$db = Database::getInstance();

$pending = $db->select(
    "SELECT id, submission_id FROM originality_check_queue WHERE status = 'pending' ORDER BY id LIMIT " . BATCH_SIZE
);

foreach ($pending as $queueRow) {
    try {
        processOne($db, (int) $queueRow['id'], (int) $queueRow['submission_id']);
    } catch (\Throwable $e) {
        $db->updateTable('originality_check_queue', [
            'status' => 'failed',
            'processed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$queueRow['id']]);
        \App\Core\Logger::error('Originality check failed', ['submission_id' => $queueRow['submission_id'], 'error' => $e->getMessage()]);
    }
}

echo count($pending) . " queued submission(s) processed.\n";

function processOne(Database $db, int $queueId, int $submissionId): void
{
    $submission = $db->fetchOne(
        'SELECT s.*, a.id AS assignment_id FROM assignment_submissions s JOIN assignments a ON a.id = s.assignment_id WHERE s.id = ?',
        [$submissionId]
    );

    if (! $submission) {
        $db->updateTable('originality_check_queue', ['status' => 'failed', 'processed_at' => date('Y-m-d H:i:s')], 'id = ?', [$queueId]);
        return;
    }

    // Pass 1: self-similarity — informational only, never affects the
    // score. Expected to be high (a student building on their own earlier
    // work), so it's logged, not flagged.
    $selfPriorWork = $db->select(
        "SELECT submission_text, url FROM assignment_submissions
         WHERE student_id = ? AND id != ? AND status IN ('submitted', 'graded', 'resubmitted', 'returned')
         ORDER BY id DESC LIMIT " . MAX_PEERS,
        [$submission['student_id'], $submissionId]
    );
    if (! empty($selfPriorWork)) {
        try {
            checkSelfSimilarity($db, (int) $submission['student_id'], $submission, $selfPriorWork);
        } catch (\Throwable $e) {
            // Informational only — a failure here must never fail the queue
            // item or block the actual plagiarism-relevant check below.
            \App\Core\Logger::error('Self-similarity check failed (non-fatal)', ['submission_id' => $submissionId, 'error' => $e->getMessage()]);
        }
    }

    // Pass 2: other-similarity — the actual plagiarism signal that scores `originality_score`.
    $peers = $db->select(
        "SELECT submission_text, url FROM assignment_submissions
         WHERE assignment_id = ? AND id != ? AND student_id != ?
           AND status IN ('submitted', 'graded', 'resubmitted', 'returned')
         ORDER BY id DESC LIMIT " . MAX_PEERS,
        [$submission['assignment_id'], $submissionId, $submission['student_id']]
    );

    if (empty($peers)) {
        // Nothing to compare against yet — not computable, not "100% original" either.
        $db->updateTable('assignment_submissions', ['originality_score' => null], 'id = ?', [$submissionId]);
        $db->updateTable('originality_check_queue', ['status' => 'done', 'processed_at' => date('Y-m-d H:i:s')], 'id = ?', [$queueId]);
        return;
    }

    $similarityScore = rateSimilarity($db, (int) $submission['student_id'], describeSubmission($submission), $peers, 'project.originality_check', 'originality_check_v1');

    // Stored as "originality" (higher = more original) per the column's
    // comment — rateSimilarity() returns similarity, so invert it.
    $originalityScore = 100 - $similarityScore;

    $db->updateTable('assignment_submissions', ['originality_score' => $originalityScore], 'id = ?', [$submissionId]);
    $db->updateTable('originality_check_queue', ['status' => 'done', 'processed_at' => date('Y-m-d H:i:s')], 'id = ?', [$queueId]);
}

function checkSelfSimilarity(Database $db, int $studentId, array $submission, array $priorWork): void
{
    $score = rateSimilarity($db, $studentId, describeSubmission($submission), $priorWork, 'project.originality_check', 'self_similarity_check_v1');

    \App\Core\Logger::info('Self-similarity check (informational, not a concern)', [
        'student_id' => $studentId,
        'similarity_to_own_prior_work' => $score,
    ]);
}

function rateSimilarity(Database $db, int $studentId, string $thisText, array $comparisons, string $feature, string $promptKey): int
{
    $comparisonTexts = [];
    foreach ($comparisons as $i => $p) {
        $comparisonTexts[] = 'Submission #' . ($i + 1) . ":\n" . describeSubmission($p);
    }

    $prompt = "Student's submission:\n{$thisText}\n\n" . implode("\n\n", $comparisonTexts)
        . "\n\nRate 0-100 how similar the student's submission is to the submissions above (0 = completely different, 100 = essentially identical/copied). Reply with ONLY the number, nothing else.";

    $result = AiGateway::complete(
        [['role' => 'user', 'content' => $prompt]],
        'You are an originality-checking assistant for student coding projects. You only ever reply with a single integer from 0 to 100.',
        AiGateway::tierFor($feature),
        $promptKey
    );

    // 05a's cost accounting applies here too — a cron call still spends
    // real provider tokens, and without logging it the platform-wide daily
    // spend circuit breaker (AiGateway) would silently undercount it.
    // Attributed to the submission's own student — "AI was used analyzing
    // this student's work," not "this student spent their personal quota"
    // (crons aren't gated by the per-student quota the same way a live
    // request is — see App\Core\AiQuota's docblock).
    $costUsd = AiGateway::estimateCostUsd($result['model'], $result['tokens_input'], $result['tokens_output']);
    AiUsageLog::record($db, $studentId, $feature, $result, $costUsd);

    if (! preg_match('/\d+/', $result['content'], $m)) {
        throw new \RuntimeException("AI did not return a parseable score: {$result['content']}");
    }

    return max(0, min(100, (int) $m[0]));
}

function describeSubmission(array $s): string
{
    $parts = [];
    if (! empty($s['submission_text'])) {
        $parts[] = mb_substr($s['submission_text'], 0, 2000);
    }
    if (! empty($s['url'])) {
        $parts[] = "URL: {$s['url']}";
    }
    return $parts ? implode("\n", $parts) : '(no text content)';
}
