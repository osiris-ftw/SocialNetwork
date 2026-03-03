<?php

namespace App\Services;

use App\Config\RedisClient;

class QueueService
{
    private $redis;
    private const QUEUE_KEY = 'queue:jobs';

    public function __construct()
    {
        $this->redis = RedisClient::getInstance()->getClient();
    }

    public function push(string $job, array $data): void
    {
        $payload = json_encode([
            'job' => $job,
            'data' => $data,
            'attempts' => 0,
            'created_at' => time(),
        ]);

        $this->redis->rpush(self::QUEUE_KEY, $payload);
    }

    public function pop(): ?array
    {
        $payload = $this->redis->lpop(self::QUEUE_KEY);
        
        if (!$payload) {
            return null;
        }

        return json_decode($payload, true);
    }

    public function size(): int
    {
        return (int)$this->redis->llen(self::QUEUE_KEY);
    }

    // Job helpers
    public function queueEmailVerification(string $email, string $username, string $token): void
    {
        $this->push('send_verification_email', [
            'email' => $email,
            'username' => $username,
            'token' => $token,
        ]);
    }

    public function queueNotification(int $userId, string $type, array $data): void
    {
        $this->push('send_notification', [
            'user_id' => $userId,
            'type' => $type,
            'data' => $data,
        ]);
    }

    public function queueMediaProcessing(string $filePath, string $type): void
    {
        $this->push('process_media', [
            'file_path' => $filePath,
            'type' => $type,
        ]);
    }
}
