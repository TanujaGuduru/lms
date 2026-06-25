<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Renders the actual certificate PDF bytes — shared by
 * cron/check-course-completion.php (first issuance) and
 * CertificateController::reissueRequest() (same file, regenerated), so
 * both produce an identical-looking document. Deliberately does NOT use
 * `certificate_templates.html_template`/`css_styles` — rendering arbitrary
 * HTML+CSS to PDF needs a headless browser or a heavy library, neither
 * available without Composer or a cloud service. See SimplePdf's docblock
 * for the full reasoning; this is a plain, real, honest certificate, not a
 * stand-in for the templated one.
 */
class CertificateGenerator
{
    public static function generate(array $certificate, string $studentName, string $courseTitle): string
    {
        $appConfig = require BASE_PATH . '/config/app.php';
        $verifyUrl = rtrim($appConfig['frontend_url'], '/') . '/verify/' . $certificate['verification_code'];

        $pdf = new SimplePdf();
        $pdf->addCenteredLine('CERTIFICATE OF COMPLETION', 22);
        $pdf->addLine('');
        $pdf->addCenteredLine('This certifies that', 12);
        $pdf->addCenteredLine($studentName, 18);
        $pdf->addCenteredLine('has successfully completed', 12);
        $pdf->addCenteredLine($courseTitle, 16);
        $pdf->addLine('');
        $pdf->addLine('Issued: ' . date('F j, Y', strtotime($certificate['issued_at'])));
        $pdf->addLine('Certificate No: ' . $certificate['certificate_number']);
        $pdf->addLine('Verify at: ' . $verifyUrl);

        $relativePath = "certificates/{$certificate['user_id']}/{$certificate['certificate_number']}.pdf";
        $absolutePath = FileStorage::absolutePath($relativePath);
        @mkdir(dirname($absolutePath), 0755, true);
        file_put_contents($absolutePath, $pdf->toBytes());

        return $relativePath;
    }
}
