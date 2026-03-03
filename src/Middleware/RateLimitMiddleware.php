<?php

namespace App\Middleware;

use App\Config\Config;
use App\Config\RedisClient;

class RateLimitMiddleware
{
    private $redis;
    private bool $enabled;
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct()
    {
        $config = Config::getInstance();
        $this->redis = RedisClient::getInstance()->getClient();
        $this->enabled = $config->get('rate_limit.enabled');
        $this->maxRequests = $config->get('rate_limit.max_requests');
        $this->windowSeconds = $config->get('rate_limit.window_seconds');
    }

    public function check(string $identifier, int $maxRequests = null, int $windowSeconds = null): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $maxRequests = $maxRequests ?? $this->maxRequests;
        $windowSeconds = $windowSeconds ?? $this->windowSeconds;

        $key = "rate_limit:{$identifier}";
        $current = (int)$this->redis->get($key);

        if ($current >= $maxRequests) {
            $this->rateLimitExceeded($maxRequests, $windowSeconds);
            return false;
        }

        // Increment counter
        $this->redis->incr($key);
        
        // Set expiration if this is the first request
        if ($current === 0) {
            $this->redis->expire($key, $windowSeconds);
        }

        return true;
    }

    public function checkByIp(int $maxRequests = null, int $windowSeconds = null): bool
    {
        $ip = $this->getClientIp();
        return $this->check("ip:{$ip}", $maxRequests, $windowSeconds);
    }

    public function checkByUser(int $userId, int $maxRequests = null, int $windowSeconds = null): bool
    {
        return $this->check("user:{$userId}", $maxRequests, $windowSeconds);
    }

    public function remaining(string $identifier): int
    {
        if (!$this->enabled) {
            return $this->maxRequests;
        }

        $key = "rate_limit:{$identifier}";
        $current = (int)$this->redis->get($key);
        
        return max(0, $this->maxRequests - $current);
    }

    public function reset(string $identifier): void
    {
        $key = "rate_limit:{$identifier}";
        $this->redis->del($key);
    }

    private function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        return trim($ip);
    }

    private function rateLimitExceeded(int $maxRequests, int $windowSeconds): void
    {
        http_response_code(429);
        header('Retry-After: ' . $windowSeconds);
        echo json_encode([
            'error' => 'Too many requests',
            'message' => "Rate limit exceeded. Maximum {$maxRequests} requests per {$windowSeconds} seconds.",
            'retry_after' => $windowSeconds,
        ]);
        exit;
    }
}
