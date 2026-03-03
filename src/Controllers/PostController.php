<?php

namespace App\Controllers;

use App\Models\Post;
use App\Models\Event;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;

class PostController
{
    private Post $postModel;
    private Event $eventModel;
    private AuthMiddleware $authMiddleware;
    private RateLimitMiddleware $rateLimiter;

    public function __construct()
    {
        $this->postModel = new Post();
        $this->eventModel = new Event();
        $this->authMiddleware = new AuthMiddleware();
        $this->rateLimiter = new RateLimitMiddleware();
    }

    public function handle(string $method, array $parts): void
    {
        $postId = $parts[0] ?? null;
        $action = $parts[1] ?? null;

        if (!$postId) {
            // List posts or create new post
            if ($method === 'GET') {
                $this->getFeed();
            } elseif ($method === 'POST') {
                $this->createPost();
            } else {
                $this->methodNotAllowed();
            }
            return;
        }

        switch ($action) {
            case null:
                if ($method === 'GET') {
                    $this->getPost($postId);
                } elseif ($method === 'DELETE') {
                    $this->deletePost($postId);
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'like':
                if ($method === 'POST') {
                    $this->likePost($postId);
                } elseif ($method === 'DELETE') {
                    $this->unlikePost($postId);
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'likes':
                if ($method === 'GET') {
                    $this->getLikes($postId);
                } else {
                    $this->methodNotAllowed();
                }
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Post endpoint not found']);
                break;
        }
    }

    private function getFeed(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $limit = (int)($_GET['limit'] ?? 20);
        $offset = (int)($_GET['offset'] ?? 0);

        $posts = $this->postModel->getFeed($currentUser['id'], $limit, $offset);

        echo json_encode(['posts' => $posts]);
    }

    private function createPost(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $this->rateLimiter->checkByUser($currentUser['id'], 20, 3600); // 20 posts per hour

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['content'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Content is required']);
            return;
        }

        $media = [
            'type' => $data['media_type'] ?? 'none',
            'url' => $data['media_url'] ?? null,
            'thumbnail' => $data['media_thumbnail_url'] ?? null,
            'visibility' => $data['visibility'] ?? 'public',
        ];

        try {
            $postId = $this->postModel->createPost($currentUser['id'], $data['content'], $media);

            // Log event
            $this->eventModel->logEvent(
                $currentUser['id'],
                'post_created',
                'post',
                $postId
            );

            $post = $this->postModel->getPost($postId, $currentUser['id']);

            http_response_code(201);
            echo json_encode([
                'message' => 'Post created successfully',
                'post' => $post,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create post']);
        }
    }

    private function getPost(int $postId): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        $userId = $currentUser['id'] ?? null;

        $post = $this->postModel->getPost($postId, $userId);

        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Post not found']);
            return;
        }

        echo json_encode(['post' => $post]);
    }

    private function deletePost(int $postId): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        if ($this->postModel->deletePost($postId, $currentUser['id'])) {
            echo json_encode(['message' => 'Post deleted successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Failed to delete post']);
        }
    }

    private function likePost(int $postId): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        if ($this->postModel->likePost($postId, $currentUser['id'])) {
            // Log event
            $this->eventModel->logEvent(
                $currentUser['id'],
                'post_liked',
                'post',
                $postId
            );

            echo json_encode(['message' => 'Post liked successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Already liked or post not found']);
        }
    }

    private function unlikePost(int $postId): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        if ($this->postModel->unlikePost($postId, $currentUser['id'])) {
            echo json_encode(['message' => 'Post unliked successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Not liked or post not found']);
        }
    }

    private function getLikes(int $postId): void
    {
        $limit = (int)($_GET['limit'] ?? 50);
        $likes = $this->postModel->getLikes($postId, $limit);

        echo json_encode(['likes' => $likes]);
    }

    private function methodNotAllowed(): void
    {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}
