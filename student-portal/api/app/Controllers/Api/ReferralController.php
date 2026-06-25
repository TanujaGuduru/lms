<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Referral System — docs/student-module/04g-apis-completion-growth.md.
 * No "create a referral" endpoint by design — a new lead arriving via
 * `?ref=` is ordinary lead-attribution metadata captured by the existing
 * public lead-capture flow, not a parallel referral funnel (the doc's
 * explicit reasoning).
 */
class ReferralController extends Controller
{
    public function myCode(Request $request): void
    {
        $userId = (int) $this->currentUser()['id'];

        $existing = $this->db->fetchOne('SELECT code FROM referral_codes WHERE user_id = ?', [$userId]);
        $code = $existing['code'] ?? $this->generateAndStoreCode($userId);

        $appConfig = require BASE_PATH . '/config/app.php';
        $this->success([
            'code' => $code,
            'share_url' => rtrim($appConfig['frontend_url'], '/') . '/join?ref=' . $code,
        ]);
    }

    public function index(Request $request): void
    {
        $userId = (int) $this->currentUser()['id'];

        $rows = $this->db->select(
            'SELECT referral_code, status, reward_type, reward_value, reward_status, converted_at
             FROM referrals WHERE referrer_user_id = ? ORDER BY created_at DESC',
            [$userId]
        );

        $this->success(array_map(fn (array $r) => [
            'referral_code' => $r['referral_code'],
            'status' => $r['status'],
            'reward_type' => $r['reward_type'],
            'reward_value' => $r['reward_value'] !== null ? (float) $r['reward_value'] : null,
            'reward_status' => $r['reward_status'],
            'converted_at' => $r['converted_at'],
        ], $rows));
    }

    /** Generated lazily on first request — most users never visit this screen (04g's explicit reasoning). */
    private function generateAndStoreCode(int $userId): string
    {
        $user = $this->db->fetchOne('SELECT first_name FROM users WHERE id = ?', [$userId]);
        $base = strtoupper(preg_replace('/[^A-Za-z]/', '', $user['first_name']) ?: 'USER');

        do {
            $code = substr($base, 0, 8) . random_int(1000, 9999);
            $taken = $this->db->fetchOne('SELECT 1 FROM referral_codes WHERE code = ?', [$code]);
        } while ($taken);

        $this->db->insertInto('referral_codes', ['user_id' => $userId, 'code' => $code]);

        return $code;
    }
}
