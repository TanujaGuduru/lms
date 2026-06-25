<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Credit Wallet — docs/student-module/04a-apis-conventions-enrollment-billing.md.
 */
class WalletController extends Controller
{
    public function show(Request $request): void
    {
        $wallet = $this->resolveWallet($request);
        $this->success($this->shapeWallet($wallet));
    }

    public function transactions(Request $request): void
    {
        $wallet = $this->resolveWallet($request);

        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));

        $result = $this->db->paginate(
            'SELECT * FROM credit_transactions WHERE wallet_id = ? ORDER BY created_at DESC',
            [$wallet['id']],
            $page,
            $perPage
        );

        $this->success($result['data'], [
            'current_page' => $result['current_page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
            'last_page' => $result['last_page'],
        ]);
    }

    /**
     * Stub gateway session — no real payment gateway (Razorpay/Stripe/etc.)
     * is configured in this pass. Creates a real `payments` row (status
     * 'pending') so the rest of the ledger has something genuine to point
     * at; the actual gateway redirect/webhook handling is future work once
     * real gateway credentials exist.
     */
    public function purchase(Request $request): void
    {
        $wallet = $this->resolveWallet($request);
        $credits = (int) $request->input('credits', 0);

        if ($credits < 1) {
            $this->fail('A positive credit amount is required.', ['credits' => ['required|integer|min:1']]);
        }

        $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $amount = $credits * 100; // placeholder pricing — real rates come from fee_structures once wired up

        $paymentId = $this->db->insertInto('payments', [
            'user_id' => (int) $this->currentUser()['id'],
            'invoice_number' => $invoiceNumber,
            'amount' => $amount,
            'total_amount' => $amount,
            'gateway' => 'manual',
            'status' => 'pending',
        ]);

        $this->success([
            'payment_id' => (int) $paymentId,
            'invoice_number' => $invoiceNumber,
            'amount' => $amount,
            'gateway_session' => null, // populated once a real gateway integration exists
            'wallet_id' => (int) $wallet['id'],
        ]);
    }

    public function freeze(Request $request): void
    {
        $wallet = $this->resolveWallet($request);
        $reason = (string) $request->input('reason', '');
        $effectiveDate = (string) $request->input('effective_date', '');

        if (! $effectiveDate || strtotime($effectiveDate) < strtotime(date('Y-m-d'))) {
            $this->fail('A valid, non-past effective date is required.', ['effective_date' => ['required|date|after_or_equal:today']]);
        }

        if ($wallet['status'] === 'frozen') {
            $this->fail("Wallet is already frozen since {$wallet['frozen_at']}.", ['reason' => ['already_frozen']], 422);
        }

        $this->db->insertInto('wallet_freeze_log', [
            'wallet_id' => $wallet['id'],
            'action' => 'frozen',
            'reason' => $reason ?: null,
            'effective_date' => $effectiveDate,
            'requested_by' => (int) $this->currentUser()['id'],
        ]);

        // Status deliberately stays 'active' here — a scheduled job flips it
        // on the effective date itself, so a class already booked before
        // then completes normally rather than being yanked mid-cycle (04a).
        // That scheduled job is a background/cron concern, not part of this
        // API surface.
        $this->success([
            'wallet_id' => (int) $wallet['id'],
            'status' => $wallet['status'],
            'pending_freeze_effective' => $effectiveDate,
        ]);
    }

    public function resume(Request $request): void
    {
        $wallet = $this->resolveWallet($request);

        if ($wallet['status'] === 'frozen') {
            $frozenDays = $wallet['frozen_at']
                ? (int) ceil((time() - strtotime($wallet['frozen_at'])) / 86400)
                : 0;

            $this->db->transaction(function () use ($wallet, $frozenDays) {
                $this->db->updateTable('credit_wallets', [
                    'status' => 'active',
                    'frozen_at' => null,
                    'frozen_reason' => null,
                    'expiry_date' => $wallet['expiry_date']
                        ? date('Y-m-d', strtotime($wallet['expiry_date'] . " +{$frozenDays} days"))
                        : null,
                ], 'id = ?', [$wallet['id']]);

                $this->db->insertInto('wallet_freeze_log', [
                    'wallet_id' => $wallet['id'],
                    'action' => 'resumed',
                    'effective_date' => date('Y-m-d'),
                    'requested_by' => (int) $this->currentUser()['id'],
                ]);
            });

            $this->success(['wallet_id' => (int) $wallet['id'], 'status' => 'active']);
            return;
        }

        // Not currently frozen — but a freeze may still be scheduled for a
        // future effective_date and just hasn't been applied by the
        // scheduled job yet. Resuming in that window cancels the pending
        // freeze rather than acting on a freeze that was never applied.
        // Must be the *latest* log entry specifically — an old 'frozen' row
        // that a later 'resumed' row already cancelled must not be found
        // again (this exact bug shipped once: calling resume twice both
        // "succeeded" because the query had no way to see the row that
        // already cancelled it).
        $latest = $this->db->fetchOne(
            'SELECT action, effective_date FROM wallet_freeze_log WHERE wallet_id = ? ORDER BY id DESC LIMIT 1',
            [$wallet['id']]
        );
        $pendingFreeze = $latest && $latest['action'] === 'frozen' && $latest['effective_date'] >= date('Y-m-d');

        if (! $pendingFreeze) {
            $this->fail('Wallet is not frozen and has no pending freeze to cancel.', ['reason' => ['not_frozen']], 422);
        }

        $this->db->insertInto('wallet_freeze_log', [
            'wallet_id' => $wallet['id'],
            'action' => 'resumed',
            'reason' => 'Cancelled before taking effect',
            'effective_date' => date('Y-m-d'),
            'requested_by' => (int) $this->currentUser()['id'],
        ]);

        $this->success(['wallet_id' => (int) $wallet['id'], 'status' => $wallet['status']]);
    }

    /**
     * A student always means their own wallet. A parent must pass
     * ?student_id= for the child in context (04a's auth conventions:
     * "different permission set applied per-request... for whichever child
     * is in context") and is gated by that specific link's can_view_billing.
     */
    private function resolveWallet(Request $request): array
    {
        $user = $this->currentUser();

        if ($user['role_slug'] === 'parent') {
            $studentId = (int) $request->input('student_id', 0);
            if (! $studentId) {
                $this->fail('student_id is required for a parent request.', ['student_id' => ['required']]);
            }

            $link = $this->db->fetchOne(
                'SELECT can_view_billing FROM parent_student_links WHERE parent_id = ? AND student_id = ?',
                [(int) $user['id'], $studentId]
            );

            if (! $link) {
                $this->fail('No such linked student.', ['reason' => ['not_found']], 404);
            }
            if (! $link['can_view_billing']) {
                $this->fail('Billing is not visible to this guardian.', ['reason' => ['billing_not_visible_to_this_guardian']], 403);
            }
        } else {
            $studentId = (int) $user['id'];
        }

        $wallet = $this->db->fetchOne(
            "SELECT cw.* FROM credit_wallets cw
             JOIN enrollments e ON e.id = cw.enrollment_id
             WHERE cw.student_id = ? AND e.status = 'active'
             ORDER BY cw.created_at DESC LIMIT 1",
            [$studentId]
        );

        if (! $wallet) {
            $this->fail('No active wallet found.', ['reason' => ['no_active_wallet']], 404);
        }

        return $wallet;
    }

    private function shapeWallet(array $wallet): array
    {
        return [
            'wallet_id' => (int) $wallet['id'],
            'status' => $wallet['status'],
            'credits_purchased' => (int) $wallet['credits_purchased'],
            'credits_consumed' => (int) $wallet['credits_consumed'],
            'credits_balance' => (int) $wallet['credits_balance'],
            'low_balance_threshold' => (int) $wallet['low_balance_threshold'],
            'expiry_date' => $wallet['expiry_date'],
        ];
    }
}
