<?php

namespace App\Config;

use Dotenv\Dotenv;

class Config
{
    private static ?Config $instance = null;
    private array $config;

    private function __construct()
    {
        // Load environment variables
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
        $dotenv->safeLoad();

        $this->config = [
            'app' => [
                'name' => $_ENV['APP_NAME'] ?? 'Social Network',
                'env' => $_ENV['APP_ENV'] ?? 'production',
                'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
            ],
            'database' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_PORT'] ?? 3306,
                'database' => $_ENV['DB_DATABASE'] ?? 'social_network',
                'username' => $_ENV['DB_USERNAME'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
            ],
            'redis' => [
                'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
                'port' => $_ENV['REDIS_PORT'] ?? 6379,
                'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            ],
            'session' => [
                'driver' => $_ENV['SESSION_DRIVER'] ?? 'redis',
                'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 120),
            ],
            'websocket' => [
                'host' => $_ENV['WEBSOCKET_HOST'] ?? '0.0.0.0',
                'port' => (int)($_ENV['WEBSOCKET_PORT'] ?? 8081),
                'allowed_origins' => explode(',', $_ENV['WEBSOCKET_ALLOWED_ORIGINS'] ?? 'http://localhost:8000'),
            ],
            'pusher' => [
                'app_id' => $_ENV['PUSHER_APP_ID'] ?? '',
                'key' => $_ENV['PUSHER_APP_KEY'] ?? '',
                'secret' => $_ENV['PUSHER_APP_SECRET'] ?? '',
                'cluster' => $_ENV['PUSHER_APP_CLUSTER'] ?? 'mt1',
                'use_pusher' => filter_var($_ENV['USE_PUSHER'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ],
            'mail' => [
                'driver' => $_ENV['MAIL_DRIVER'] ?? 'smtp',
                'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
                'port' => (int)($_ENV['MAIL_PORT'] ?? 25),
                'username' => $_ENV['MAIL_USERNAME'] ?? '',
                'password' => $_ENV['MAIL_PASSWORD'] ?? '',
                'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
                'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com',
                'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Social Network',
            ],
            'upload' => [
                'max_size' => (int)($_ENV['MAX_UPLOAD_SIZE'] ?? 10485760),
                'allowed_image_types' => explode(',', $_ENV['ALLOWED_IMAGE_TYPES'] ?? 'jpg,jpeg,png,gif,webp'),
                'allowed_video_types' => explode(',', $_ENV['ALLOWED_VIDEO_TYPES'] ?? 'mp4,webm,mov'),
                'path' => $_ENV['UPLOAD_PATH'] ?? __DIR__ . '/../../public/uploads',
            ],
            'cdn' => [
                'enabled' => filter_var($_ENV['CDN_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'url' => $_ENV['CDN_URL'] ?? '',
            ],
            'rate_limit' => [
                'enabled' => filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'max_requests' => (int)($_ENV['RATE_LIMIT_MAX_REQUESTS'] ?? 60),
                'window_seconds' => (int)($_ENV['RATE_LIMIT_WINDOW_SECONDS'] ?? 60),
            ],
            'security' => [
                'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'change_this_secret',
                'password_hash_algo' => $_ENV['PASSWORD_HASH_ALGO'] ?? 'argon2id',
                'bcrypt_rounds' => (int)($_ENV['BCRYPT_ROUNDS'] ?? 12),
            ],
        ];
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function all(): array
    {
        return $this->config;
    }
}
