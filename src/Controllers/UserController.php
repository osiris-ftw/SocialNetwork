<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Post;
use App\Services\AuthService;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Middleware\AuthMiddleware;

class UserController
{
    private User $userModel;
    private Post $postModel;
    private AuthMiddleware $authMiddleware;
    private FileUploadService $uploadService;
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->userModel = new User();
        $this->postModel = new Post();
        $this->authMiddleware = new AuthMiddleware();
        $this->uploadService = new FileUploadService();
        $this->notificationService = new NotificationService();
    }

    public function handle(string $method, array $parts): void
    {
        $userId = $parts[0] ?? null;
        $action = $parts[1] ?? null;

        // Search endpoint: GET /users/search?q=...
        if ($userId === 'search') {
            if ($method === 'GET') {
                $this->searchUsers();
            } else {
                $this->methodNotAllowed();
            }
            return;
        }

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

        // Username lookup: GET /users/{username} (non-numeric, no action)
        if (!is_numeric($userId) && $action === null) {
            if ($method === 'GET') {
                $this->getProfileByUsername($userId);
            } else {
                $this->methodNotAllowed();
            }
            return;
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

            case 'mutuals':
                if ($method === 'GET') {
                    $this->getMutuals($userId);
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
        $currentUser = $this->authMiddleware->authenticate(silent: true);
        $requesterId = $currentUser ? (int)$currentUser['id'] : null;

        $isFollowing = $requesterId ? $this->userModel->isFollowing($requesterId, $userId) : false;
        $isFollowedBack = $requesterId ? $this->userModel->isFollowing($userId, $requesterId) : false;

        echo json_encode([
            'id' => $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'] ?? $user['username'],
            'bio' => $user['bio'],
            'avatar_url' => $user['avatar_url'],
            'cover_photo_url' => $user['cover_photo_url'],
            'location' => $user['location'],
            'website' => $user['website'],
            'created_at' => $user['created_at'],
            'is_following' => $isFollowing,
            'is_followed_back' => $isFollowedBack,
            'counts' => [
                'posts' => (int)($stats['posts_count'] ?? 0),
                'followers' => (int)($stats['followers_count'] ?? 0),
                'following' => (int)($stats['following_count'] ?? 0),
            ],
            'stats' => $stats,
        ]);
    }

    private function getProfileByUsername(string $username): void
    {
        $user = $this->userModel->findByUsername($username);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        $userId = (int)$user['id'];
        $stats = $this->userModel->getStats($userId);
        $currentUser = $this->authMiddleware->authenticate(silent: true);
        $requesterId = $currentUser ? (int)$currentUser['id'] : null;

        $isFollowing = $requesterId ? $this->userModel->isFollowing($requesterId, $userId) : false;
        $isFollowedBack = $requesterId ? $this->userModel->isFollowing($userId, $requesterId) : false;

        echo json_encode([
            'id' => $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'] ?? $user['username'],
            'bio' => $user['bio'],
            'avatar_url' => $user['avatar_url'],
            'cover_photo_url' => $user['cover_photo_url'],
            'location' => $user['location'],
            'website' => $user['website'],
            'created_at' => $user['created_at'],
            'is_following' => $isFollowing,
            'is_followed_back' => $isFollowedBack,
            'counts' => [
                'posts' => (int)($stats['posts_count'] ?? 0),
                'followers' => (int)($stats['followers_count'] ?? 0),
                'following' => (int)($stats['following_count'] ?? 0),
            ],
            'stats' => $stats,
        ]);
    }

    private function searchUsers(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $q = trim($_GET['q'] ?? '');
        $limit = min((int)($_GET['limit'] ?? 20), 50);
        $offset = (int)($_GET['offset'] ?? 0);

        if (strlen($q) < 1) {
            echo json_encode(['users' => []]);
            return;
        }

        $users = $this->userModel->search($q, $limit, $offset, (int)$currentUser['id']);

        echo json_encode(['users' => $users]);
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

        $currentUser = $this->authMiddleware->authenticate(silent: true);
        $requesterId = $currentUser ? (int)$currentUser['id'] : null;

        $posts = $this->postModel->getUserPosts($userId, $requesterId, $limit, $offset);

        echo json_encode(['posts' => $posts]);
    }

    private function getFollowers(int $userId): void
    {
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        $currentUser = $this->authMiddleware->authenticate(silent: true);
        $requesterId = $currentUser ? (int)$currentUser['id'] : null;

        $followers = $this->userModel->getFollowers($userId, $limit, $offset, $requesterId);

        echo json_encode(['followers' => $followers]);
    }

    private function getFollowing(int $userId): void
    {
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        $currentUser = $this->authMiddleware->authenticate(silent: true);
        $requesterId = $currentUser ? (int)$currentUser['id'] : null;

        $following = $this->userModel->getFollowing($userId, $limit, $offset, $requesterId);

        echo json_encode(['following' => $following]);
    }

    private function getMutuals(int $userId): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        if ((int)$currentUser['id'] !== (int)$userId) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        $mutuals = $this->userModel->getMutualFollows($userId, $limit, $offset);
        echo json_encode(['mutuals' => $mutuals]);
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

        if ($this->userModel->follow((int)$currentUser['id'], $targetUserId)) {
            // Fire notification (non-fatal)
            try {
                $this->notificationService->notifyNewFollower($targetUserId, (int)$currentUser['id']);
            } catch (\Exception $e) {
                error_log('Follow notification error: ' . $e->getMessage());
            }

            $stats = $this->userModel->getStats($targetUserId);
            echo json_encode([
                'message' => 'Successfully followed user',
                'followers_count' => (int)($stats['followers_count'] ?? 0),
                'following_count' => (int)($stats['following_count'] ?? 0),
                'is_following' => true,
            ]);
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

        if ($this->userModel->unfollow((int)$currentUser['id'], $targetUserId)) {
            $stats = $this->userModel->getStats($targetUserId);
            echo json_encode([
                'message' => 'Successfully unfollowed user',
                'followers_count' => (int)($stats['followers_count'] ?? 0),
                'following_count' => (int)($stats['following_count'] ?? 0),
                'is_following' => false,
            ]);
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
