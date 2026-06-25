<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;

/**
 * Parent Consent — docs/student-module/04a-apis-conventions-enrollment-billing.md.
 *
 * The doc's `{linkId}` path parameter assumes `parent_student_links` has a
 * surrogate id column — the real table (database/schema.sql) has a
 * composite primary key `(parent_id, student_id)` only. Since the parent
 * side of the link is always the authenticated caller, `{linkId}` is treated
 * as `student_id` here — the pair `(current parent, that student)` is the
 * actual link, and there's nothing else a single surrogate id could have
 * disambiguated that this doesn't already.
 */
class ParentConsentController extends Controller
{
    public function index(Request $request): void
    {
        $parentId = (int) $this->currentUser()['id'];

        $links = $this->db->select(
            "SELECT l.student_id, l.relationship, l.consent_status, u.first_name, u.last_name
             FROM parent_student_links l
             JOIN users u ON u.id = l.student_id
             WHERE l.parent_id = ? AND l.consent_status = 'pending'",
            [$parentId]
        );

        $this->success($links);
    }

    public function initiate(Request $request, string $studentId): void
    {
        $parentId = (int) $this->currentUser()['id'];
        $link = $this->requireLink($parentId, (int) $studentId);

        if ($link['consent_status'] !== 'pending') {
            $this->fail('This consent has already been resolved.', ['reason' => ['not_pending']], 422);
        }

        $otp = (string) random_int(100000, 999999);
        $this->db->insertInto('parent_consent_otps', [
            'parent_id' => $parentId,
            'student_id' => (int) $studentId,
            'otp_code' => $otp,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
        ]);

        // No WhatsApp/SMS gateway configured in this pass — logged instead of
        // actually sent; this is exactly the Communication Engine's job
        // (docs/student-module/06) once that integration exists.
        Logger::info('Parent consent OTP issued', ['parent_id' => $parentId, 'student_id' => $studentId, 'otp' => $otp]);

        $this->success(['message' => 'A verification code has been sent.', 'expires_in_minutes' => 10]);
    }

    public function grant(Request $request, string $studentId): void
    {
        $parentId = (int) $this->currentUser()['id'];
        $link = $this->requireLink($parentId, (int) $studentId);

        if ($link['consent_status'] !== 'pending') {
            $this->fail('This consent has already been resolved.', ['reason' => ['not_pending']], 422);
        }

        $method = (string) $request->input('method', 'otp_verified');

        if ($method === 'otp_verified') {
            $otpCode = (string) $request->input('otp_code', '');
            $otp = $this->db->fetchOne(
                'SELECT id FROM parent_consent_otps
                 WHERE parent_id = ? AND student_id = ? AND otp_code = ? AND consumed_at IS NULL AND expires_at > NOW()
                 ORDER BY id DESC LIMIT 1',
                [$parentId, $studentId, $otpCode]
            );

            if (! $otp) {
                $this->fail('That code is incorrect or has expired.', ['otp_code' => ['invalid_or_expired']], 422);
            }

            $this->db->updateTable('parent_consent_otps', ['consumed_at' => date('Y-m-d H:i:s')], 'id = ?', [$otp['id']]);
        }

        $now = date('Y-m-d H:i:s');

        $this->db->transaction(function () use ($parentId, $studentId, $method, $now) {
            $this->db->updateTable('parent_student_links', [
                'consent_status' => 'granted',
                'consent_method' => $method,
                'consent_recorded_at' => $now,
            ], 'parent_id = ? AND student_id = ?', [$parentId, $studentId]);

            // If this was the student's only pending consent gate, flip the
            // account live in the same transaction — not a separate
            // follow-up step that could fail independently and leave the
            // account stuck (04a's explicit requirement).
            $stillPending = $this->db->count(
                'parent_student_links',
                'student_id = ? AND consent_status = ?',
                [$studentId, 'pending']
            );

            if ($stillPending === 0) {
                $this->db->updateTable('users', ['status' => 'active'], "id = ? AND status = 'pending'", [$studentId]);
            }
        });

        $this->success([
            'student_id' => (int) $studentId,
            'consent_status' => 'granted',
            'consent_recorded_at' => $now,
        ]);
    }

    public function revoke(Request $request, string $studentId): void
    {
        $parentId = (int) $this->currentUser()['id'];
        $this->requireLink($parentId, (int) $studentId);

        $this->db->updateTable('parent_student_links', [
            'consent_status' => 'revoked',
        ], 'parent_id = ? AND student_id = ?', [$parentId, $studentId]);

        $this->success(['student_id' => (int) $studentId, 'consent_status' => 'revoked']);
    }

    private function requireLink(int $parentId, int $studentId): array
    {
        $link = $this->db->fetchOne(
            'SELECT * FROM parent_student_links WHERE parent_id = ? AND student_id = ?',
            [$parentId, $studentId]
        );

        if (! $link) {
            $this->fail('No such parent-student link.', ['reason' => ['not_found']], 404);
        }

        return $link;
    }
}
