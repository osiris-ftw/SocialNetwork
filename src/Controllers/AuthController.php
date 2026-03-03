<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Event;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Services\QueueService;
use App\Middleware\RateLimitMiddleware;

class AuthController
{
    private User $userModel;
    private Event $eventModel;
    private AuthService $authService;
    private EmailService $emailService;
    private QueueService $queueService;
    private RateLimitMiddleware $rateLimiter;

    public function __construct()
    {
        $this->userModel = new User();
        $this->eventModel = new Event();
        $this->authService = new AuthService();
        $this->emailService = new EmailService();
        $this->queueService = new QueueService();
        $this->rateLimiter = new RateLimitMiddleware();
    }

    public function handle(string $method, array $parts): void
    {
        $action = $parts[0] ?? '';

        switch ($action) {
            case 'signup':
                if ($method === 'POST') {
                    $this->signup();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'login':
                if ($method === 'POST') {
                    $this->login();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'verify-email':
                if ($method === 'POST') {
                    $this->verifyEmail();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'resend-verification':
                if ($method === 'POST') {
                    $this->resendVerification();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Auth endpoint not found']);
                break;
        }
    }

    private function signup(): void
    {
        // Rate limiting
        $this->rateLimiter->checkByIp(5, 300); // 5 signups per 5 minutes

        $data = json_decode(file_get_contents('php://input'), true);

        // Validate input
        $errors = [];
        if (empty($data['username'])) {
            $errors[] = 'Username is required';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $data['username'])) {
            $errors[] = 'Username must be 3-50 characters (letters, numbers, underscore only)';
        }

        if (empty($data['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        if (empty($data['password'])) {
            $errors[] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['errors' => $errors]);
            return;
        }

        // Check if user already exists
        if ($this->userModel->findByEmail($data['email'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already registered']);
            return;
        }

        if ($this->userModel->findByUsername($data['username'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Username already taken']);
            return;
        }

        try {
            $userId = $this->userModel->createUser($data);

            // Get the created user with verification token
            $user = $this->userModel->find($userId);

            // Queue verification email
            $this->queueService->queueEmailVerification(
                $user['email'],
                $user['username'],
                $user['email_verification_token']
            );

            // Log event
            $this->eventModel->logEvent(
                $userId,
                'user_signup',
                'user',
                $userId,
                null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );

            // Generate token
            $token = $this->authService->generateToken($userId, $user['email']);

            http_response_code(201);
            echo json_encode([
                'message' => 'User created successfully. Please check your email to verify your account.',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'email_verified' => false,
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create user: ' . $e->getMessage()]);
        }
    }

    private function login(): void
    {
        // Rate limiting
        $this->rateLimiter->checkByIp(10, 60); // 10 logins per minute

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email and password are required']);
            return;
        }

        $user = $this->userModel->findByEmail($data['email']);

        if (!$user || !$this->userModel->verifyPassword($data['password'], $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            return;
        }

        if ($user['is_banned']) {
            http_response_code(403);
            echo json_encode(['error' => 'Your account has been banned: ' . $user['banned_reason']]);
            return;
        }

        // Log event
        $this->eventModel->logEvent(
            $user['id'],
            'user_login',
            'user',
            $user['id'],
            null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        $token = $this->authService->generateToken($user['id'], $user['email']);

        echo json_encode([
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'email_verified' => (bool)$user['email_verified_at'],
                'avatar_url' => $user['avatar_url'],
                'is_admin' => (bool)$user['is_admin'],
            ],
        ]);
    }

    private function verifyEmail(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['token'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Verification token is required']);
            return;
        }

        if ($this->userModel->verifyEmail($data['token'])) {
            echo json_encode(['message' => 'Email verified successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or expired verification token']);
        }
    }

    private function resendVerification(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email is required']);
            return;
        }

        $user = $this->userModel->findByEmail($data['email']);

        if (!$user) {
            // Don't reveal if email exists
            echo json_encode(['message' => 'If the email exists, a verification email has been sent']);
            return;
        }

        if ($user['email_verified_at']) {
            echo json_encode(['message' => 'Email already verified']);
            return;
        }

        // Queue verification email
        $this->queueService->queueEmailVerification(
            $user['email'],
            $user['username'],
            $user['email_verification_token']
        );

        echo json_encode(['message' => 'Verification email sent']);
    }

    private function methodNotAllowed(): void
    {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}
