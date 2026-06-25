<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class NotificationService
{
    public static function send(
        int    $userId,
        string $type,
        string $title,
        string $message,
        array  $data = []
    ): int {
        return (int) Database::getInstance()->insert(
            "INSERT INTO notifications (user_id, type, title, message, data, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())",
            [$userId, $type, $title, $message, json_encode($data)]
        );
    }

    /**
     * Insert a notification row for each user in one bulk INSERT per 500-user chunk.
     *
     * @param int[] $userIds
     */
    public static function broadcast(
        array  $userIds,
        string $type,
        string $title,
        string $message,
        array  $data = []
    ): void {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (empty($userIds)) {
            return;
        }

        $db       = Database::getInstance();
        $dataJson = json_encode($data);

        foreach (array_chunk($userIds, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,0,NOW())'));
            $params       = [];
            foreach ($chunk as $uid) {
                array_push($params, (int)$uid, $type, $title, $message, $dataJson);
            }
            $db->query(
                "INSERT INTO notifications (user_id, type, title, message, data, is_read, created_at)
                 VALUES {$placeholders}",
                $params
            );
        }
    }
}
