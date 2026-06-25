<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run nightly, e.g. 2:00 AM:
 *   php /home/yourusername/public_html/api/cron/compute-progress-snapshots.php
 *
 * The only writer of `student_progress_snapshots` — docs/student-module/04e
 * is explicit that this is a precomputed, up-to-24h-stale view by design,
 * never a live aggregation, since multi-table joins on every dashboard load
 * is a real performance risk on shared hosting.
 *
 * `coding_success_rate` means something different here than originally
 * designed: the original spec was "share of sandbox executions with
 * exit_code=0" — there is no Coding Sandbox in this build at all (no live
 * code execution, by deliberate choice), so there's nothing to execute and
 * measure. This computes it instead as the share of graded coding-type work
 * (type='code' assignments + type='coding' exam questions) that scored at
 * or above a passing bar — a grading-based "did it work" signal, the
 * closest honest equivalent left. `coding_executions_count` stays 0 always,
 * truthfully, since no execution ever happens.
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\Database;

const CODING_QUESTION_PASS_RATIO = 0.6; // no per-question passing mark exists; a flat, documented default.

$db = Database::getInstance();

$enrollments = $db->select("SELECT id, user_id, course_id, batch_id FROM enrollments WHERE status = 'active'");

$count = 0;
foreach ($enrollments as $enrollment) {
    computeSnapshot($db, $enrollment);
    $count++;
}

echo "{$count} active enrollment(s) snapshotted.\n";

function computeSnapshot(Database $db, array $enrollment): void
{
    $studentId = (int) $enrollment['user_id'];
    $courseId = (int) $enrollment['course_id'];
    $batchId = $enrollment['batch_id'];

    $attendanceAvg = $batchId
        ? $db->fetchOne(
            "SELECT AVG(a.attendance_percent) AS v FROM attendance a
             JOIN live_classes lc ON lc.id = a.live_class_id
             WHERE a.student_id = ? AND lc.batch_id = ?",
            [$studentId, $batchId]
        )['v']
        : null;
    // NULL (no sessions recorded yet) must stay NULL, not become a
    // misleadingly-measured "0% attendance" — same principle as
    // exam_responses.marks_awarded elsewhere in this build.
    $attendancePercent = $attendanceAvg !== null ? (int) round((float) $attendanceAvg) : null;

    $totalLessons = (int) $db->fetchOne(
        "SELECT COUNT(*) AS c FROM lessons WHERE course_id = ? AND is_published = 1 AND deleted_at IS NULL AND completion_required = 1",
        [$courseId]
    )['c'];
    $completedLessons = (int) $db->fetchOne(
        "SELECT COUNT(*) AS c FROM lesson_progress WHERE enrollment_id = ? AND status = 'completed'",
        [$enrollment['id']]
    )['c'];
    $courseCompletionPercent = $totalLessons > 0 ? (int) round($completedLessons / $totalLessons * 100) : null;

    $totalAssignments = (int) $db->fetchOne(
        "SELECT COUNT(*) AS c FROM assignments WHERE course_id = ? AND status IN ('published', 'closed')",
        [$courseId]
    )['c'];
    $completedAssignments = (int) $db->fetchOne(
        "SELECT COUNT(*) AS c FROM assignment_submissions asub
         JOIN assignments a ON a.id = asub.assignment_id
         WHERE asub.student_id = ? AND a.course_id = ? AND asub.status IN ('graded', 'submitted', 'resubmitted')",
        [$studentId, $courseId]
    )['c'];
    $assignmentCompletionPercent = $totalAssignments > 0 ? (int) round($completedAssignments / $totalAssignments * 100) : null;

    $avgProjectScoreRow = $db->fetchOne(
        "SELECT AVG(asub.marks_awarded / a.total_marks * 100) AS v
         FROM assignment_submissions asub JOIN assignments a ON a.id = asub.assignment_id
         WHERE asub.student_id = ? AND a.course_id = ? AND a.type = 'project'
           AND asub.status = 'graded' AND asub.marks_awarded IS NOT NULL",
        [$studentId, $courseId]
    );
    $avgProjectScore = $avgProjectScoreRow['v'] !== null ? round((float) $avgProjectScoreRow['v'], 2) : null;

    $avgAssessmentScoreRow = $db->fetchOne(
        "SELECT AVG(ea.percentage) AS v FROM exam_attempts ea JOIN exams e ON e.id = ea.exam_id
         WHERE ea.user_id = ? AND e.course_id = ? AND e.type != 'placement' AND ea.status = 'completed'",
        [$studentId, $courseId]
    );
    $avgAssessmentScore = $avgAssessmentScoreRow['v'] !== null ? round((float) $avgAssessmentScoreRow['v'], 2) : null;

    $codingSuccessRate = computeCodingSuccessRate($db, $studentId, $courseId);

    $aiMessagesCount = (int) $db->fetchOne(
        "SELECT COUNT(*) AS c FROM ai_messages am JOIN ai_conversations ac ON ac.id = am.conversation_id
         WHERE ac.student_id = ? AND ac.linked_course_id = ? AND am.role = 'user'",
        [$studentId, $courseId]
    )['c'];

    $db->execute(
        "INSERT INTO student_progress_snapshots
            (student_id, enrollment_id, snapshot_date, attendance_percent, course_completion_percent,
             assignment_completion_percent, avg_project_score, avg_assessment_score,
             ai_messages_count, coding_executions_count, coding_success_rate)
         VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 0, ?)
         ON DUPLICATE KEY UPDATE
            attendance_percent = VALUES(attendance_percent),
            course_completion_percent = VALUES(course_completion_percent),
            assignment_completion_percent = VALUES(assignment_completion_percent),
            avg_project_score = VALUES(avg_project_score),
            avg_assessment_score = VALUES(avg_assessment_score),
            ai_messages_count = VALUES(ai_messages_count),
            coding_success_rate = VALUES(coding_success_rate),
            computed_at = NOW()",
        [
            $studentId, $enrollment['id'], $attendancePercent, $courseCompletionPercent,
            $assignmentCompletionPercent, $avgProjectScore, $avgAssessmentScore,
            $aiMessagesCount, $codingSuccessRate,
        ]
    );
}

function computeCodingSuccessRate(Database $db, int $studentId, int $courseId): ?float
{
    $codeAssignments = $db->fetchOne(
        "SELECT COUNT(*) AS total, SUM(asub.marks_awarded >= a.passing_marks) AS passed
         FROM assignment_submissions asub JOIN assignments a ON a.id = asub.assignment_id
         WHERE asub.student_id = ? AND a.course_id = ? AND a.type = 'code'
           AND asub.status = 'graded' AND asub.marks_awarded IS NOT NULL",
        [$studentId, $courseId]
    );

    $codingQuestions = $db->fetchOne(
        "SELECT COUNT(*) AS total, SUM(er.marks_awarded >= q.marks * " . CODING_QUESTION_PASS_RATIO . ") AS passed
         FROM exam_responses er
         JOIN questions q ON q.id = er.question_id
         JOIN exam_attempts ea ON ea.id = er.attempt_id
         JOIN exams e ON e.id = ea.exam_id
         WHERE ea.user_id = ? AND e.course_id = ? AND q.type = 'coding' AND er.marks_awarded IS NOT NULL",
        [$studentId, $courseId]
    );

    $total = (int) $codeAssignments['total'] + (int) $codingQuestions['total'];
    if ($total === 0) {
        return null;
    }

    $passed = (int) $codeAssignments['passed'] + (int) $codingQuestions['passed'];
    return round($passed / $total * 100, 2);
}
