<?php

namespace App\Models;

class Notification extends BaseModel
{
    protected string $table = 'notifications';

    public function createNotification(
        int $userId,
        ?int $actorId,
        string $type,
        string $entityType,
        int $entityId,
        ?string $content = null
    ): int {
        return $this->create([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'type' => $type,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'content' => $content,
        ]);
    }

    public function getUserNotifications(int $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->query(
            "SELECT n.*, 
                    u.username as actor_username, 
                    u.avatar_url as actor_avatar
             FROM notifications n
             LEFT JOIN users u ON n.actor_id = u.id
             WHERE n.user_id = ?
             ORDER BY n.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            [$userId]
        );
    }

    public function getUnreadNotifications(int $userId, int $limit = 50): array
    {
        return $this->query(
            "SELECT n.*, 
                    u.username as actor_username, 
                    u.avatar_url as actor_avatar
             FROM notifications n
             LEFT JOIN users u ON n.actor_id = u.id
             WHERE n.user_id = ? AND n.is_read = 0
             ORDER BY n.created_at DESC
             LIMIT {$limit}",
            [$userId]
        );
    }

    public function getUnreadCount(int $userId): int
    {
        $result = $this->queryOne(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        );

        return (int)($result['count'] ?? 0);
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        return $this->execute(
            "UPDATE notifications SET is_read = 1, read_at = NOW() 
             WHERE id = ? AND user_id = ?",
            [$notificationId, $userId]
        );
    }

    public function markAllAsRead(int $userId): bool
    {
        return $this->execute(
            "UPDATE notifications SET is_read = 1, read_at = NOW() 
             WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    public function deleteNotification(int $notificationId, int $userId): bool
    {
        return $this->execute(
            "DELETE FROM notifications WHERE id = ? AND user_id = ?",
            [$notificationId, $userId]
        );
    }
}
