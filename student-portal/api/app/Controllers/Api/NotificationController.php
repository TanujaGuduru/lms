<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Student/parent-facing read side of the shared `notifications` table —
 * docs/student-module/06-communication-engine.md. The Admin panel already
 * has its own equivalent (app\Controllers\Api\NotificationController.php
 * at the repo root) reading/writing the same table, but it's a separate PHP
 * application with its own auth/routing — a student or parent authenticated
 * against THIS API has no way to reach that controller, so this is a real,
 * previously-missing read endpoint for this app, same shape as every other
 * "the table existed, nothing served it from this side" gap found across
 * this build. App\Core\Notifier is what writes the rows this reads.
 */
class NotificationController extends Controller
{
    public function index(Request $request): void
    {
        $userId = (int) $this->currentUser()['id'];

        $rows = $this->db->select(
            'SELECT id, type, title, message, data, icon, color, action_url, is_read, created_at
             FROM notifications WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 50',
            [$userId]
        );
        $unreadCount = $this->db->count('notifications', 'user_id = ? AND is_read = 0 AND deleted_at IS NULL', [$userId]);

        foreach ($rows as &$row) {
            $row['is_read'] = (bool) $row['is_read'];
            $row['data'] = $row['data'] ? json_decode($row['data'], true) : [];
        }

        $this->success($rows, ['unread_count' => $unreadCount]);
    }

    public function markRead(Request $request, string $id): void
    {
        $userId = (int) $this->currentUser()['id'];
        $this->db->updateTable(
            'notifications',
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            'id = ? AND user_id = ?',
            [$id, $userId]
        );
        $this->success(true);
    }

    public function markAllRead(Request $request): void
    {
        $userId = (int) $this->currentUser()['id'];
        $this->db->updateTable(
            'notifications',
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            'user_id = ? AND is_read = 0',
            [$userId]
        );
        $this->success(true);
    }

    public function destroy(Request $request, string $id): void
    {
        $userId = (int) $this->currentUser()['id'];
        $this->db->updateTable(
            'notifications',
            ['deleted_at' => date('Y-m-d H:i:s')],
            'id = ? AND user_id = ?',
            [$id, $userId]
        );
        $this->success(true);
    }
}
