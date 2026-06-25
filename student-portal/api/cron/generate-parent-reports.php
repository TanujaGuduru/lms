<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run once a month, e.g. the 1st at 3:00 AM:
 *   php /home/yourusername/public_html/api/cron/generate-parent-reports.php
 *
 * Fills a gap docs/student-module/04f doesn't actually cover: that doc only
 * specifies the *read* side of Monthly Parent Reports (list/detail/mark-
 * viewed) — something still has to create the `parent_reports` rows in the
 * first place. Pulls the same numbers as the Progress Analytics snapshot
 * (04e) plus any elevated risk signal (cron/compute-risk-scores.php, 05c
 * §4 — the score itself is always deterministic math, never an LLM; the
 * LLM's role starts after a score already exists, narrating it for a
 * parent to read), asks the AI Gateway for a structured summary per 05c
 * §5, and writes a real PDF via App\Core\SimplePdf to local disk —
 * `pdf_url` stores a relative path (same convention as Materials/
 * Recordings' `file_path`), not a literal URL; ReportController signs it
 * fresh on each read via App\Core\FileStorage, so the link never goes stale.
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\AiGateway;
use App\Core\AiUsageLog;
use App\Core\Database;
use App\Core\FileStorage;
use App\Core\Notifier;
use App\Core\SimplePdf;

$db = Database::getInstance();

$periodMonth = date('Y-m-01', strtotime('first day of last month'));
$periodEnd = date('Y-m-t', strtotime($periodMonth));

$enrollments = $db->select(
    "SELECT e.id AS enrollment_id, e.user_id AS student_id, e.enrolled_at, u.first_name, u.last_name, c.title AS course_title
     FROM enrollments e JOIN users u ON u.id = e.user_id JOIN courses c ON c.id = e.course_id
     WHERE e.status = 'active'"
);

$count = 0;
foreach ($enrollments as $enrollment) {
    if (alreadyGenerated($db, (int) $enrollment['student_id'], (int) $enrollment['enrollment_id'], $periodMonth)) {
        continue;
    }
    generateReport($db, $enrollment, $periodMonth, $periodEnd);
    $count++;
}

echo "{$count} parent report(s) generated for period {$periodMonth}.\n";

function alreadyGenerated(Database $db, int $studentId, int $enrollmentId, string $periodMonth): bool
{
    return (bool) $db->fetchOne(
        'SELECT 1 FROM parent_reports WHERE student_id = ? AND enrollment_id = ? AND period_month = ?',
        [$studentId, $enrollmentId, $periodMonth]
    );
}

function generateReport(Database $db, array $enrollment, string $periodMonth, string $periodEnd): void
{
    $snapshot = $db->fetchOne(
        "SELECT * FROM student_progress_snapshots
         WHERE enrollment_id = ? AND snapshot_date <= ?
         ORDER BY snapshot_date DESC LIMIT 1",
        [$enrollment['enrollment_id'], $periodEnd]
    );

    // Advisory only, never the raw score — the exact same principle
    // ParentController::riskSummary() already applies (04f), restated here
    // because this is the other surface where a risk score reaches a parent.
    $hasElevatedRisk = (bool) $db->fetchOne(
        "SELECT 1 FROM risk_scores WHERE student_id = ? AND intervention_status IN ('mentor_call_scheduled', 'parent_escalated')
         ORDER BY computed_at DESC LIMIT 1",
        [$enrollment['student_id']]
    );

    $isPartialPeriod = strtotime($enrollment['enrolled_at']) > strtotime($periodMonth);

    $sections = buildSummary($db, $enrollment, $snapshot, $isPartialPeriod, $hasElevatedRisk);
    $relativePath = "reports/{$enrollment['student_id']}/{$periodMonth}.pdf";
    writePdf($enrollment, $snapshot, $periodMonth, $sections, $isPartialPeriod, $relativePath);

    $reportId = $db->insertInto('parent_reports', [
        'student_id' => $enrollment['student_id'],
        'enrollment_id' => $enrollment['enrollment_id'],
        'period_month' => $periodMonth,
        'is_partial_period' => $isPartialPeriod ? 1 : 0,
        'pdf_url' => $relativePath,
        'summary_text' => $sections['raw'],
    ]);

    notifyParents($db, $enrollment, $periodMonth, (int) $reportId);
}

/**
 * The recipient is the PARENT, via parent_student_links — not the student
 * the report is about. Batchable (06 §4/§6) with `batch_key = "family:{parent_id}"`,
 * the family-level case 06 §4 explicitly separates from per-student
 * batching: multiple children's *separate* report-ready notifications for
 * the same parent combine into one delivery, while the underlying
 * `parent_reports` documents themselves stay one-per-child (06 §4's
 * explicit point — never merge the report content itself).
 */
function notifyParents(Database $db, array $enrollment, string $periodMonth, int $reportId): void
{
    $parents = $db->select('SELECT parent_id FROM parent_student_links WHERE student_id = ?', [$enrollment['student_id']]);
    $context = [
        'student_name' => trim("{$enrollment['first_name']} {$enrollment['last_name']}"),
        'month_year' => date('F Y', strtotime($periodMonth)),
    ];

    foreach ($parents as $parent) {
        $parentId = (int) $parent['parent_id'];
        Notifier::send($parentId, "monthly_parent_report_ready:{$reportId}", $context, "family:{$parentId}");
    }
}

/**
 * 05c §5 — three labeled sections (strengths / areas for growth /
 * recommended next steps), not one undifferentiated paragraph, with an
 * explicit grounding instruction (no hallucinated specifics in a document
 * a paying parent reads as authoritative) and a framing instruction that
 * changes entirely for a partial-period report.
 */
function buildSummary(Database $db, array $enrollment, array|false $snapshot, bool $isPartialPeriod, bool $hasElevatedRisk): array
{
    $stats = $snapshot ? sprintf(
        "Attendance: %s%%. Course completion: %s%%. Assignment completion: %s%%. Avg project score: %s. Avg assessment score: %s.",
        $snapshot['attendance_percent'] ?? 'no data',
        $snapshot['course_completion_percent'] ?? 'no data',
        $snapshot['assignment_completion_percent'] ?? 'no data',
        $snapshot['avg_project_score'] ?? 'no data',
        $snapshot['avg_assessment_score'] ?? 'no data'
    ) : 'No activity data is available yet for this period.';

    $riskLine = $hasElevatedRisk ? "\nAn elevated engagement/dropout-risk signal was detected this period (no further detail provided)." : '';
    $partialInstruction = $isPartialPeriod
        ? 'This student joined partway through this period. Frame all percentages/trends as "in their first X days" rather than implying a full month\'s pattern — a low percentage that is really just "joined recently" must not read as alarming.'
        : '';

    $systemPrompt = 'You write monthly progress reports for a parent of a student at a coding school. The audience is the parent, not the student — tone and vocabulary should suit an adult reader, not a child. '
        . 'Use only the data provided below. Do not invent specific events, dates, or numbers not present in this data. If something is not covered by the provided data, do not speculate about it. '
        . 'Structure your response as exactly three labeled sections: "Strengths:", "Areas for growth:", "Recommended next steps:". Each section is 1-3 sentences. '
        . 'If an elevated risk signal is mentioned in the data, mention it gently in Areas for growth and tie it to one concrete, specific recommended action in Recommended next steps (e.g. a suggested check-in call) — never state a numeric score or use clinical-sounding language. '
        . ($partialInstruction ? $partialInstruction . ' ' : '')
        . 'Never alarmist. No raw jargon like "API" or internal metric names.';

    try {
        $result = AiGateway::complete(
            [['role' => 'user', 'content' => "Student: {$enrollment['first_name']} {$enrollment['last_name']}, course: {$enrollment['course_title']}. Stats for the month: {$stats}{$riskLine}"]],
            $systemPrompt,
            AiGateway::tierFor('parent_report.summary'),
            'parent_report_summary_v2'
        );

        // 05a's cost accounting applies to every Gateway call, including
        // this cron's — see process-originality-checks.php's identical note.
        $costUsd = AiGateway::estimateCostUsd($result['model'], $result['tokens_input'], $result['tokens_output']);
        AiUsageLog::record($db, (int) $enrollment['student_id'], 'parent_report.summary', $result, $costUsd);

        return ['raw' => $result['content']] + parseSections($result['content']);
    } catch (\Throwable $e) {
        \App\Core\Logger::error('Parent report AI summary failed', ['enrollment_id' => $enrollment['enrollment_id'], 'error' => $e->getMessage()]);
        $fallback = "Progress summary for this period: {$stats}";
        return ['raw' => $fallback, 'strengths' => $fallback, 'growth' => '', 'next_steps' => ''];
    }
}

function parseSections(string $text): array
{
    $pattern = '/Strengths:(.*?)Areas for growth:(.*?)Recommended next steps:(.*)/is';
    if (preg_match($pattern, $text, $m)) {
        return ['strengths' => trim($m[1]), 'growth' => trim($m[2]), 'next_steps' => trim($m[3])];
    }
    // Model didn't follow the exact labels — show the raw text under one heading rather than losing it.
    return ['strengths' => trim($text), 'growth' => '', 'next_steps' => ''];
}

function writePdf(array $enrollment, array|false $snapshot, string $periodMonth, array $sections, bool $isPartialPeriod, string $relativePath): void
{
    $pdf = new SimplePdf();
    $pdf->addHeading("Monthly Progress Report - " . date('F Y', strtotime($periodMonth)));
    $pdf->addLine("Student: {$enrollment['first_name']} {$enrollment['last_name']}");
    $pdf->addLine("Course: {$enrollment['course_title']}");
    if ($isPartialPeriod) {
        $pdf->addLine('(Partial month - student joined partway through this period)');
    }
    $pdf->addLine('');

    $pdf->addHeading('Strengths');
    $pdf->addParagraph($sections['strengths'] ?: 'No data available yet for this period.');

    if ($sections['growth']) {
        $pdf->addHeading('Areas for growth');
        $pdf->addParagraph($sections['growth']);
    }

    if ($sections['next_steps']) {
        $pdf->addHeading('Recommended next steps');
        $pdf->addParagraph($sections['next_steps']);
    }

    if ($snapshot) {
        $pdf->addHeading('This month at a glance');
        $pdf->addLine('Attendance: ' . ($snapshot['attendance_percent'] !== null ? $snapshot['attendance_percent'] . '%' : 'No data'));
        $pdf->addLine('Course completion: ' . ($snapshot['course_completion_percent'] !== null ? $snapshot['course_completion_percent'] . '%' : 'No data'));
        $pdf->addLine('Assignment completion: ' . ($snapshot['assignment_completion_percent'] !== null ? $snapshot['assignment_completion_percent'] . '%' : 'No data'));
        $pdf->addLine('Average project score: ' . ($snapshot['avg_project_score'] !== null ? $snapshot['avg_project_score'] : 'No data'));
        $pdf->addLine('Average assessment score: ' . ($snapshot['avg_assessment_score'] !== null ? $snapshot['avg_assessment_score'] : 'No data'));
    }

    $absolutePath = FileStorage::absolutePath($relativePath);
    @mkdir(dirname($absolutePath), 0755, true);
    file_put_contents($absolutePath, $pdf->toBytes());
}
