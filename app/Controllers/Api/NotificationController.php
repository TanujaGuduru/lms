<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;

class NotificationController
{
    public function index(Request $request): void
    {
        $user = Auth::user();
        if (!$user) { $this->json(['success'=>false,'message'=>'Unauthenticated'], 401); return; }

        $db = Database::getInstance();

        $notifications = $db->fetchAll(
            "SELECT id, type, title, message, data, is_read, created_at
             FROM notifications
             WHERE user_id = ? AND deleted_at IS NULL
             ORDER BY created_at DESC
             LIMIT 50",
            [$user['id']]
        );

        $unreadCount = (int)$db->fetchOne(
            "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0",
            [$user['id']]
        )['cnt'];

        foreach ($notifications as &$n) {
            $n['data'] = json_decode($n['data'] ?? '{}', true) ?: [];
        }

        $this->json([
            'success'      => true,
            'data'         => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, int $id): void
    {
        $user = Auth::user();
        if (!$user) { $this->json(['success'=>false,'message'=>'Unauthenticated'], 401); return; }

        Database::getInstance()->execute(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?",
            [$id, $user['id']]
        );

        $this->json(['success'=>true,'message'=>'Marked as read']);
    }

    public function markAllRead(Request $request): void
    {
        $user = Auth::user();
        if (!$user) { $this->json(['success'=>false,'message'=>'Unauthenticated'], 401); return; }

        Database::getInstance()->execute(
            "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0",
            [$user['id']]
        );

        $this->json(['success'=>true,'message'=>'All notifications marked as read']);
    }

    public function destroy(Request $request, int $id): void
    {
        $user = Auth::user();
        if (!$user) { $this->json(['success'=>false,'message'=>'Unauthenticated'], 401); return; }

        Database::getInstance()->execute(
            "UPDATE notifications SET deleted_at = NOW() WHERE id = ? AND user_id = ?",
            [$id, $user['id']]
        );

        $this->json(['success'=>true,'message'=>'Notification deleted']);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
