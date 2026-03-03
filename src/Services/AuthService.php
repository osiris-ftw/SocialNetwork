<?php

namespace App\Services;

use App\Config\Config;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService
{
    private string $jwtSecret;
    private int $jwtExpiration = 86400; // 24 hours

    public function __construct()
    {
        $config = Config::getInstance();
        $this->jwtSecret = $config->get('security.jwt_secret');
    }

    public function generateToken(int $userId, string $email): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->jwtExpiration;

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'user_id' => $userId,
            'email' => $email,
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    public function verifyToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return (array)$decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getUserIdFromToken(string $token): ?int
    {
        $payload = $this->verifyToken($token);
        return $payload['user_id'] ?? null;
    }

    public function extractTokenFromHeader(?string $authHeader): ?string
    {
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
