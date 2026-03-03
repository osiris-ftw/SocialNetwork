<?php

namespace App\Middleware;

use App\Services\AuthService;
use App\Models\User;

class AuthMiddleware
{
    private AuthService $authService;
    private User $userModel;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->userModel = new User();
    }

    public function authenticate(bool $silent = false): ?array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        
        if (!$authHeader) {
            if (!$silent) {
                $this->respondUnauthorized('Missing authorization header');
            }
            return null;
        }

        $token = $this->authService->extractTokenFromHeader($authHeader);
        
        if (!$token) {
            if (!$silent) {
                $this->respondUnauthorized('Invalid authorization header');
            }
            return null;
        }

        $payload = $this->authService->verifyToken($token);
        
        if (!$payload) {
            if (!$silent) {
                $this->respondUnauthorized('Invalid or expired token');
            }
            return null;
        }

        $userId = $payload['user_id'] ?? null;
        
        if (!$userId) {
            $this->respondUnauthorized('Invalid token payload');
            return null;
        }

        $user = $this->userModel->find($userId);
        
        if (!$user) {
            $this->respondUnauthorized('User not found');
            return null;
        }

        if ($user['is_banned']) {
            $this->respondForbidden('Your account has been banned');
            return null;
        }

        return $user;
    }

    public function requireEmailVerification(array $user): bool
    {
        if (!$user['email_verified_at']) {
            $this->respondForbidden('Email verification required');
            return false;
        }

        return true;
    }

    public function requireAdmin(array $user): bool
    {
        if (!$user['is_admin']) {
            $this->respondForbidden('Admin access required');
            return false;
        }

        return true;
    }

    private function respondUnauthorized(string $message): void
    {
        http_response_code(401);
        echo json_encode(['error' => $message]);
        exit;
    }

    private function respondForbidden(string $message): void
    {
        http_response_code(403);
        echo json_encode(['error' => $message]);
        exit;
    }
}
