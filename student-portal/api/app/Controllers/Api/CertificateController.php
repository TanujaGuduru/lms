<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\CertificateGenerator;
use App\Core\Controller;
use App\Core\FileStorage;
use App\Core\Request;

/**
 * Certificates — docs/student-module/04g-apis-completion-growth.md.
 * `certificates.pdf_path` already exists in the real schema but is never
 * populated anywhere in the Admin panel (no PDF-generation code exists at
 * all there) — this is the first real producer of that file. See
 * App\Core\CertificateGenerator/SimplePdf for what's deliberately not
 * attempted (the `certificate_templates` HTML+CSS rendering system) and why.
 */
class CertificateController extends Controller
{
    public function index(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];

        $rows = $this->db->select(
            "SELECT c.id, c.certificate_number, c.issued_at, c.is_revoked, co.title AS course_title
             FROM certificates c JOIN courses co ON co.id = c.course_id
             WHERE c.user_id = ? ORDER BY c.issued_at DESC",
            [$studentId]
        );

        $this->success(array_map(fn (array $r) => [
            'id' => (int) $r['id'],
            'certificate_number' => $r['certificate_number'],
            'course_title' => $r['course_title'],
            'issued_at' => $r['issued_at'],
            'is_revoked' => (bool) $r['is_revoked'],
        ], $rows));
    }

    public function show(Request $request, string $id): void
    {
        $certificate = $this->ownCertificate($id);

        $this->success([
            'id' => (int) $certificate['id'],
            'certificate_number' => $certificate['certificate_number'],
            'verification_code' => $certificate['verification_code'],
            'course_title' => $certificate['course_title'],
            'issued_at' => $certificate['issued_at'],
            'is_revoked' => (bool) $certificate['is_revoked'],
        ]);
    }

    public function download(Request $request, string $id): void
    {
        $certificate = $this->ownCertificate($id);

        if (! $certificate['pdf_path']) {
            $this->fail('This certificate has no file yet.', ['reason' => ['not_generated']], 404);
        }

        $this->success([
            'url' => FileStorage::signedUrl($certificate['pdf_path'], 600),
            'expires_in' => 600,
        ]);
    }

    /** Pre-issuance only — fires as part of the near-completion flow, before auto-issuance (04g's explicit framing). */
    public function confirmCertificateName(Request $request, string $enrollmentId): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $enrollment = $this->db->fetchOne('SELECT id FROM enrollments WHERE id = ? AND user_id = ?', [$enrollmentId, $studentId]);

        if (! $enrollment) {
            $this->fail('No such enrollment.', ['reason' => ['not_found']], 404);
        }

        $existingCertificate = $this->db->fetchOne(
            "SELECT id FROM certificates WHERE enrollment_id = ? AND is_revoked = 0",
            [$enrollmentId]
        );
        if ($existingCertificate) {
            $this->fail('A certificate has already been issued for this enrollment — use reissue-request instead.', ['reason' => ['already_issued']], 422);
        }

        $name = trim((string) $request->input('name_on_certificate', ''));
        if (! $name) {
            $this->fail('name_on_certificate is required.', ['name_on_certificate' => ['required']]);
        }

        $this->db->updateTable('enrollments', ['confirmed_certificate_name' => $name], 'id = ?', [$enrollmentId]);
        $this->success(['confirmed' => true]);
    }

    /** Regenerates the PDF under the same certificate_number/verification_code — never a new row (04g's explicit reasoning). */
    public function reissueRequest(Request $request, string $id): void
    {
        $certificate = $this->ownCertificate($id);

        if ($certificate['is_revoked']) {
            $this->fail('This certificate has been revoked and cannot be reissued.', ['reason' => ['revoked']], 422);
        }

        $studentName = $this->certificateName($certificate);
        $relativePath = CertificateGenerator::generate($certificate, $studentName, $certificate['course_title']);
        $this->db->updateTable('certificates', ['pdf_path' => $relativePath], 'id = ?', [$certificate['id']]);

        $this->success(['certificate_id' => (int) $certificate['id'], 'status' => 'regenerating']);
    }

    private function certificateName(array $certificate): string
    {
        if (! empty($certificate['confirmed_certificate_name'])) {
            return $certificate['confirmed_certificate_name'];
        }
        return trim($certificate['first_name'] . ' ' . $certificate['last_name']);
    }

    private function ownCertificate(string $id): array
    {
        $studentId = (int) $this->currentUser()['id'];

        $certificate = $this->db->fetchOne(
            "SELECT c.*, co.title AS course_title, u.first_name, u.last_name, e.confirmed_certificate_name
             FROM certificates c
             JOIN courses co ON co.id = c.course_id
             JOIN users u ON u.id = c.user_id
             LEFT JOIN enrollments e ON e.id = c.enrollment_id
             WHERE c.id = ? AND c.user_id = ?",
            [$id, $studentId]
        );

        if (! $certificate) {
            $this->fail('No such certificate.', ['reason' => ['not_found']], 404);
        }

        return $certificate;
    }
}
