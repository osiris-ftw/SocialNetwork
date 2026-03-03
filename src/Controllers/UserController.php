<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Post;
use App\Services\AuthService;
use App\Services\FileUploadService;
use App\Middleware\AuthMiddleware;

class UserController
{
    private User $userModel;
    private Post $postModel;
    private AuthMiddleware $authMiddleware;
    private FileUploadService $uploadService;

    public function __construct()
    {
        $this->userModel = new User();
        $this->postModel = new Post();
        $this->authMiddleware = new AuthMiddleware();
        $this->uploadService = new FileUploadService();
    }

    public function handle(string $method, array $parts): void
    {
        $userId = $parts[0] ?? null;
        $action = $parts[1] ?? null;

        if (!$userId) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID required']);
            return;
        }

        if ($userId === 'me') {
            $currentUser = $this->authMiddleware->authenticate();
            if (!$currentUser) {
                return;
            }
            $userId = $currentUser['id'];
        }

        switch ($action) {
            case null:
                if ($method === 'GET') {
                    $this->getProfile($userId);
                } elseif ($method === 'PUT') {
                    $this->updateProfile($userId);
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'posts':
                if ($method === 'GET') {
                    $this->getUserPosts($userId);
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'followers':
                if ($method === 'GET') {
                    $this->getFollowers($userId);
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'following':
                if ($method === 'GET') {
                    $this->getFollowing($userId);
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'follow':
                if ($method === 'POST') {
                    $this->follow($userId);
                } elseif ($method === 'DELETE') {
                    $this->unfollow($userId);
                } else {
                    $this->methodNotAllowed();
                }
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'User endpoint not found']);
                break;
        }
    }

    private function getProfile(int $userId): void
    {
        $user = $this->userModel->find($userId);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        $stats = $this->userModel->getStats($userId);

        echo json_encode([
            'id' => $user['id'],
            'username' => $user['username'],
            'bio' => $user['bio'],
            'avatar_url' => $user['avatar_url'],
            'cover_photo_url' => $user['cover_photo_url'],
            'location' => $user['location'],
            'website' => $user['website'],
            'created_at' => $user['created_at'],
            'stats' => $stats,
        ]);
    }

    private function updateProfile(int $userId): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser || $currentUser['id'] != $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($this->userModel->updateProfile($userId, $data)) {
            echo json_encode(['message' => 'Profile updated successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Failed to update profile']);
        }
    }

    private function getUserPosts(int $userId): void
    {
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = (int)($_GET['offset'] ?? 0);

        $currentUser = $this->authMiddleware->authenticate();
        $requesterId = $currentUser['id'] ?? null;

        $posts = $this->postModel->getUserPosts($userId, $requesterId, $limit, $offset);

        echo json_encode(['posts' => $posts]);
    }

    private function getFollowers(int $userId): void
    {
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        $followers = $this->userModel->getFollowers($userId, $limit, $offset);

        echo json_encode(['followers' => $followers]);
    }

    private function getFollowing(int $userId): void
    {
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        $following = $this->userModel->getFollowing($userId, $limit, $offset);

        echo json_encode(['following' => $following]);
    }

    private function follow(int $targetUserId): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        if ($currentUser['id'] == $targetUserId) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot follow yourself']);
            return;
        }

        if ($this->userModel->follow($currentUser['id'], $targetUserId)) {
            echo json_encode(['message' => 'Successfully followed user']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Already following or user not found']);
        }
    }

    private function unfollow(int $targetUserId): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        if ($this->userModel->unfollow($currentUser['id'], $targetUserId)) {
            echo json_encode(['message' => 'Successfully unfollowed user']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Not following this user']);
        }
    }

    private function methodNotAllowed(): void
    {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}
