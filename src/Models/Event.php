<?php

namespace App\Models;

class Event extends BaseModel
{
    protected string $table = 'events';

    public function logEvent(
        ?int $userId,
        string $eventType,
        string $entityType = 'none',
        ?int $entityId = null,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): int {
        return $this->create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    public function getEventsByType(string $eventType, int $limit = 100, int $offset = 0): array
    {
        return $this->query(
            "SELECT * FROM events WHERE event_type = ? 
             ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}",
            [$eventType]
        );
    }

    public function getUserEvents(int $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->query(
            "SELECT * FROM events WHERE user_id = ? 
             ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}",
            [$userId]
        );
    }

    public function getEventStats(string $startDate, string $endDate): array
    {
        return $this->query(
            "SELECT event_type, COUNT(*) as count, DATE(created_at) as date
             FROM events
             WHERE created_at BETWEEN ? AND ?
             GROUP BY event_type, DATE(created_at)
             ORDER BY date DESC, count DESC",
            [$startDate, $endDate]
        );
    }

    public function getDailyStats(int $days = 30): array
    {
        return $this->query(
            "SELECT 
                DATE(created_at) as date,
                event_type,
                COUNT(*) as count
             FROM events
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at), event_type
             ORDER BY date DESC",
            [$days]
        );
    }

    public function getTopUsers(string $eventType, int $days = 30, int $limit = 10): array
    {
        return $this->query(
            "SELECT u.id, u.username, u.avatar_url, COUNT(*) as event_count
             FROM events e
             INNER JOIN users u ON e.user_id = u.id
             WHERE e.event_type = ? 
               AND e.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY u.id, u.username, u.avatar_url
             ORDER BY event_count DESC
             LIMIT {$limit}",
            [$eventType, $days]
        );
    }
}
