<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Payments / Refunds / Freeze — docs/student-module/04f-apis-parent-billing.md.
 * No cloud adaptation needed here — no real payment gateway (Razorpay/etc.)
 * is configured in this pass either way (same stub posture WalletController
 * already established for purchase()), and freeze/refund are pure
 * ledger/ticket operations against the existing tables.
 */
class PaymentController extends Controller
{
    public function index(Request $request): void
    {
        $userId = $this->resolveTargetUserId($request);
        $enrollmentId = $request->input('enrollment_id');

        $sql = 'SELECT * FROM payments WHERE user_id = ?';
        $params = [$userId];
        if ($enrollmentId) {
            // payments has no enrollment_id column directly — scope via the
            // course/batch the enrollment points at, the only link available.
            $enrollment = $this->db->fetchOne('SELECT course_id, batch_id FROM enrollments WHERE id = ? AND user_id = ?', [$enrollmentId, $userId]);
            if (! $enrollment) {
                $this->fail('No such enrollment.', ['reason' => ['not_found']], 404);
            }
            $sql .= ' AND course_id = ?';
            $params[] = $enrollment['course_id'];
        }
        $sql .= ' ORDER BY created_at DESC';

        $this->success($this->db->select($sql, $params));
    }

    public function show(Request $request, string $id): void
    {
        $payment = $this->ownPayment($request, $id);
        $this->success($payment);
    }

    /** Same checkout flow re-entered, not a special-cased path — a fresh payments row, same shape as WalletController::purchase(). */
    public function retry(Request $request, string $id): void
    {
        $payment = $this->ownPayment($request, $id);

        if ($payment['status'] !== 'failed') {
            $this->fail('Only a failed payment can be retried.', ['reason' => ['not_failed']], 422);
        }

        $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

        $newPaymentId = $this->db->insertInto('payments', [
            'user_id' => $payment['user_id'],
            'course_id' => $payment['course_id'],
            'batch_id' => $payment['batch_id'],
            'fee_structure_id' => $payment['fee_structure_id'],
            'invoice_number' => $invoiceNumber,
            'amount' => $payment['amount'],
            'discount_amount' => $payment['discount_amount'],
            'gst_amount' => $payment['gst_amount'],
            'total_amount' => $payment['total_amount'],
            'currency' => $payment['currency'],
            'gateway' => 'manual',
            'status' => 'pending',
            'notes' => "Retry of payment #{$payment['id']}",
        ]);

        $this->success([
            'payment_id' => (int) $newPaymentId,
            'invoice_number' => $invoiceNumber,
            'amount' => (float) $payment['total_amount'],
            'gateway_session' => null, // populated once a real gateway integration exists
        ]);
    }

    /**
     * Opens a support ticket, never an immediate refund — an admin reviews
     * against policy before anything is approved (04f's explicit
     * reasoning). Still returns a normal 200 either way, even for a
     * repeated request from the same account — silently rejecting at
     * request time would tip off exactly the abuse pattern fraud review
     * is watching for; the flagging itself happens server-side, invisibly.
     */
    public function refundRequest(Request $request): void
    {
        $userId = (int) $this->currentUser()['id'];
        $reason = trim((string) $request->input('reason', ''));
        $paymentId = $request->input('payment_id');

        if (! $reason || ! $paymentId) {
            $this->fail('reason and payment_id are required.', ['reason' => ['required'], 'payment_id' => ['required']]);
        }

        $payment = $this->db->fetchOne('SELECT id FROM payments WHERE id = ? AND user_id = ?', [$paymentId, $userId]);
        if (! $payment) {
            $this->fail('No such payment.', ['reason' => ['not_found']], 404);
        }

        $ticketNumber = 'SUP-' . date('Y') . '-' . str_pad((string) $this->nextTicketSequence(), 5, '0', STR_PAD_LEFT);

        $ticketId = $this->db->insertInto('support_tickets', [
            'ticket_number' => $ticketNumber,
            'user_id' => $userId,
            'subject' => "Refund request — Payment #{$paymentId}",
            'description' => $reason,
            'priority' => 'medium',
            'status' => 'open',
            'channel' => 'web',
        ]);

        $this->flagIfRepeatedRefundRequest($userId);

        $this->success(['ticket_id' => (int) $ticketId, 'ticket_number' => $ticketNumber, 'status' => 'open']);
    }

    public function freezeHistory(Request $request): void
    {
        $userId = $this->resolveTargetUserId($request);

        $wallet = $this->db->fetchOne(
            "SELECT id FROM credit_wallets WHERE student_id = ? ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );

        if (! $wallet) {
            $this->success([]);
        }

        $rows = $this->db->select(
            'SELECT action, reason, effective_date, created_at FROM wallet_freeze_log WHERE wallet_id = ? ORDER BY id',
            [$wallet['id']]
        );

        $this->success($rows);
    }

    /** A student always means themselves; a parent must pass ?student_id= for the child in context, gated by can_view_billing — same convention as WalletController. */
    private function resolveTargetUserId(Request $request): int
    {
        $user = $this->currentUser();

        if ($user['role_slug'] !== 'parent') {
            return (int) $user['id'];
        }

        $studentId = (int) $request->input('student_id', 0);
        if (! $studentId) {
            $this->fail('student_id is required for a parent request.', ['student_id' => ['required']]);
        }

        $link = $this->db->fetchOne(
            'SELECT can_view_billing FROM parent_student_links WHERE parent_id = ? AND student_id = ? AND consent_status = ?',
            [(int) $user['id'], $studentId, 'granted']
        );

        if (! $link) {
            $this->fail('No such linked student.', ['reason' => ['not_found']], 404);
        }
        if (! $link['can_view_billing']) {
            $this->fail('Billing is not visible to this guardian.', ['reason' => ['billing_not_visible_to_this_guardian']], 403);
        }

        return $studentId;
    }

    private function ownPayment(Request $request, string $id): array
    {
        $userId = $this->resolveTargetUserId($request);
        $payment = $this->db->fetchOne('SELECT * FROM payments WHERE id = ? AND user_id = ?', [$id, $userId]);

        if (! $payment) {
            $this->fail('No such payment.', ['reason' => ['not_found']], 404);
        }

        return $payment;
    }

    private function nextTicketSequence(): int
    {
        return (int) $this->db->fetchOne("SELECT COUNT(*) + 1 AS n FROM support_tickets WHERE ticket_number LIKE ?", ['SUP-' . date('Y') . '-%'])['n'];
    }

    private function flagIfRepeatedRefundRequest(int $userId): void
    {
        $recentCount = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM support_tickets WHERE user_id = ? AND subject LIKE 'Refund request%' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$userId]
        )['c'];

        if ($recentCount >= 3) {
            \App\Core\Logger::error('Repeated refund requests — flagged for fraud/risk review', ['user_id' => $userId, 'count_last_30_days' => $recentCount]);
        }
    }
}
