<?php

namespace App\Models;

class Message extends BaseModel
{
    protected string $table = 'messages';

    public function sendMessage(int $senderId, int $recipientId, string $content): int
    {
        return $this->create([
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'content' => $content,
            'is_delivered' => 0,
            'is_read' => 0,
        ]);
    }

    public function getConversation(int $userId, int $otherUserId, int $limit = 50, int $offset = 0, int $afterId = 0): array
    {
        $afterClause = $afterId > 0 ? 'AND m.id > ' . (int)$afterId : '';
        $order = $afterId > 0 ? 'ASC' : 'DESC';
        return $this->query(
            "SELECT m.*, 
                    sender.username as sender_username, 
                    sender.avatar_url as sender_avatar,
                    recipient.username as recipient_username,
                    recipient.avatar_url as recipient_avatar
             FROM messages m
             INNER JOIN users sender ON m.sender_id = sender.id
             INNER JOIN users recipient ON m.recipient_id = recipient.id
             WHERE ((m.sender_id = ? AND m.recipient_id = ? AND m.is_deleted_by_sender = 0)
                 OR (m.sender_id = ? AND m.recipient_id = ? AND m.is_deleted_by_recipient = 0))
             {$afterClause}
             ORDER BY m.created_at {$order}
             LIMIT {$limit} OFFSET {$offset}",
            [$userId, $otherUserId, $otherUserId, $userId]
        );
    }

    public function getConversationsList(int $userId, int $limit = 20): array
    {
        // Get latest message from each conversation
        return $this->query(
            "SELECT 
                m.*,
                CASE 
                    WHEN m.sender_id = ? THEN m.recipient_id 
                    ELSE m.sender_id 
                END as other_user_id,
                CASE 
                    WHEN m.sender_id = ? THEN recipient.username 
                    ELSE sender.username 
                END as other_username,
                CASE 
                    WHEN m.sender_id = ? THEN recipient.avatar_url 
                    ELSE sender.avatar_url 
                END as other_avatar,
                (SELECT COUNT(*) FROM messages m2 
                 WHERE m2.recipient_id = ? 
                   AND m2.sender_id = (CASE WHEN m.sender_id = ? THEN m.recipient_id ELSE m.sender_id END)
                   AND m2.is_read = 0) as unread_count
             FROM messages m
             INNER JOIN users sender ON m.sender_id = sender.id
             INNER JOIN users recipient ON m.recipient_id = recipient.id
             WHERE (m.sender_id = ? OR m.recipient_id = ?)
               AND m.id IN (
                   SELECT MAX(id) FROM messages
                   WHERE sender_id = ? OR recipient_id = ?
                   GROUP BY LEAST(sender_id, recipient_id), GREATEST(sender_id, recipient_id)
               )
             ORDER BY m.created_at DESC
             LIMIT {$limit}",
            [$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]
        );
    }

    public function markAsDelivered(int $messageId): bool
    {
        return $this->execute(
            "UPDATE messages SET is_delivered = 1, delivered_at = NOW() WHERE id = ?",
            [$messageId]
        );
    }

    public function markAsRead(int $messageId, int $userId): bool
    {
        return $this->execute(
            "UPDATE messages SET is_read = 1, read_at = NOW() 
             WHERE id = ? AND recipient_id = ?",
            [$messageId, $userId]
        );
    }

    public function markConversationAsRead(int $userId, int $otherUserId): bool
    {
        return $this->execute(
            "UPDATE messages SET is_read = 1, read_at = NOW() 
             WHERE recipient_id = ? AND sender_id = ? AND is_read = 0",
            [$userId, $otherUserId]
        );
    }

    public function deleteMessageForUser(int $messageId, int $userId): bool
    {
        $message = $this->find($messageId);
        
        if (!$message) {
            return false;
        }

        if ($message['sender_id'] == $userId) {
            return $this->update($messageId, ['is_deleted_by_sender' => true]);
        } elseif ($message['recipient_id'] == $userId) {
            return $this->update($messageId, ['is_deleted_by_recipient' => true]);
        }

        return false;
    }

    public function getUnreadCount(int $userId): int
    {
        $result = $this->queryOne(
            "SELECT COUNT(*) as count FROM messages 
             WHERE recipient_id = ? AND is_read = 0 AND is_deleted_by_recipient = 0",
            [$userId]
        );

        return (int)($result['count'] ?? 0);
    }
}
