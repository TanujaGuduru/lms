<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run hourly:
 *   php /home/yourusername/public_html/api/cron/check-course-completion.php
 *
 * docs/student-module/04g/03h describe course completion as an
 * "event-driven check (final assessment submitted, last required
 * assignment graded), never a client-triggered action." On real
 * infrastructure that would be a queue listener; on GoDaddy shared hosting
 * with no persistent process and no way for this app to be notified the
 * instant an Admin-portal teacher grades something, a periodic sweep is
 * the honest equivalent — close enough (within an hour) for something that
 * was never going to be instant on this hosting model anyway.
 *
 * For every active enrollment whose criteria (App\Core\CourseCompletion)
 * are now all met: flips status to 'completed', then auto-issues a
 * certificate — same certificate_number/verification_code format as the
 * Admin panel's own manual SuperAdmin\CertificateController::issue(), so
 * a cron-issued and a teacher-issued certificate are indistinguishable —
 * plus generates the real PDF that issue() never did (see
 * App\Core\CertificateGenerator).
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\CertificateGenerator;
use App\Core\CourseCompletion;
use App\Core\Database;
use App\Core\Notifier;

$db = Database::getInstance();

$enrollments = $db->select(
    "SELECT e.*, u.first_name, u.last_name, c.title AS course_title
     FROM enrollments e JOIN users u ON u.id = e.user_id JOIN courses c ON c.id = e.course_id
     WHERE e.status = 'active'"
);

$completedCount = 0;
foreach ($enrollments as $enrollment) {
    $requirements = CourseCompletion::evaluate($db, $enrollment);
    if (! $requirements['all_met']) {
        continue;
    }

    $db->updateTable('enrollments', [
        'status' => 'completed',
        'completed_at' => date('Y-m-d H:i:s'),
        'progress_percentage' => 100,
    ], 'id = ?', [$enrollment['id']]);

    issueCertificateIfNeeded($db, $enrollment);
    Notifier::send((int) $enrollment['user_id'], "completion_notification:{$enrollment['id']}", ['course_title' => $enrollment['course_title']]);
    $completedCount++;
}

echo "{$completedCount} enrollment(s) marked completed.\n";

function issueCertificateIfNeeded(Database $db, array $enrollment): void
{
    $alreadyIssued = $db->fetchOne(
        "SELECT id FROM certificates WHERE user_id = ? AND course_id = ? AND is_revoked = 0",
        [$enrollment['user_id'], $enrollment['course_id']]
    );
    if ($alreadyIssued) {
        return;
    }

    $template = $db->fetchOne("SELECT id FROM certificate_templates WHERE is_default = 1")
        ?: $db->fetchOne("SELECT id FROM certificate_templates LIMIT 1");
    if (! $template) {
        \App\Core\Logger::error('Cannot auto-issue certificate — no certificate_templates row configured', ['enrollment_id' => $enrollment['id']]);
        return;
    }

    // Same format as SuperAdmin\CertificateController::issue() — a cron-
    // issued certificate must be indistinguishable from a teacher-issued one.
    $certNumber = 'CG-' . strtoupper(substr(md5($enrollment['user_id'] . $enrollment['course_id'] . time()), 0, 8));
    $verifyCode = bin2hex(random_bytes(16));

    $certificateId = $db->insertInto('certificates', [
        'user_id' => $enrollment['user_id'],
        'course_id' => $enrollment['course_id'],
        'enrollment_id' => $enrollment['id'],
        'template_id' => $template['id'],
        'certificate_number' => $certNumber,
        'verification_code' => $verifyCode,
    ]);

    $studentName = $enrollment['confirmed_certificate_name'] ?: trim($enrollment['first_name'] . ' ' . $enrollment['last_name']);
    $certificate = $db->fetchOne('SELECT * FROM certificates WHERE id = ?', [$certificateId]);
    $relativePath = CertificateGenerator::generate($certificate, $studentName, $enrollment['course_title']);

    $db->updateTable('certificates', ['pdf_path' => $relativePath], 'id = ?', [$certificateId]);
    $db->updateTable('enrollments', [
        'certificate_issued_at' => date('Y-m-d H:i:s'),
        'certificate_id' => $certificateId,
    ], 'id = ?', [$enrollment['id']]);
}
