<?php

namespace App\Controllers;

use App\Models\Post;
use App\Models\Comment;
use App\Models\Event;
use App\Services\NotificationService;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;

class PostController
{
    private Post $postModel;
    private Comment $commentModel;
    private Event $eventModel;
    private NotificationService $notificationService;
    private AuthMiddleware $authMiddleware;
    private RateLimitMiddleware $rateLimiter;

    public function __construct()
    {
        $this->postModel = new Post();
        $this->commentModel = new Comment();
        $this->eventModel = new Event();
        $this->notificationService = new NotificationService();
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

            case 'comments':
                if ($method === 'GET') {
                    $this->getPostComments($postId);
                } elseif ($method === 'POST') {
                    $this->createPostComment($postId);
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
        $filterUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

        if ($filterUserId) {
            $posts = $this->postModel->getUserPosts($filterUserId, $currentUser['id'], $limit, $offset);
        } else {
            $posts = $this->postModel->getFeed($currentUser['id'], $limit, $offset);
        }

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

            // Fire notification (non-fatal)
            try {
                $post = $this->postModel->find($postId);
                if ($post && $post['user_id'] != $currentUser['id']) {
                    $this->notificationService->notifyNewLike(
                        (int)$post['user_id'],
                        (int)$currentUser['id'],
                        $postId
                    );
                }
            } catch (\Exception $notifEx) {
                error_log('Like notification error: ' . $notifEx->getMessage());
            }

            $post = $this->postModel->getPost($postId, $currentUser['id']);
            echo json_encode([
                'message' => 'Post liked successfully',
                'like_count' => $post['like_count'] ?? 0,
                'is_liked' => true,
            ]);
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
            $post = $this->postModel->getPost($postId, $currentUser['id']);
            echo json_encode([
                'message' => 'Post unliked successfully',
                'like_count' => $post['like_count'] ?? 0,
                'is_liked' => false,
            ]);
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

    private function getPostComments(int $postId): void
    {
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        $comments = $this->commentModel->getPostComments($postId, $limit, $offset);

        echo json_encode(['comments' => $comments]);
    }

    private function createPostComment(int $postId): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['content'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Content is required']);
            return;
        }

        try {
            $commentId = $this->commentModel->createComment(
                $postId,
                (int)$currentUser['id'],
                $data['content'],
                $data['parent_comment_id'] ?? null
            );

            $this->postModel->incrementCommentCount($postId);
            $this->eventModel->logEvent((int)$currentUser['id'], 'comment_created', 'comment', $commentId);

            // Notifications
            try {
                $post = $this->postModel->find($postId);
                if ($post && $post['user_id'] != $currentUser['id']) {
                    $this->notificationService->notifyNewComment(
                        (int)$post['user_id'],
                        (int)$currentUser['id'],
                        $postId,
                        $commentId
                    );
                }
                if (!empty($data['parent_comment_id'])) {
                    $parentComment = $this->commentModel->find((int)$data['parent_comment_id']);
                    if ($parentComment && $parentComment['user_id'] != $currentUser['id']) {
                        $this->notificationService->notifyCommentReply(
                            (int)$parentComment['user_id'],
                            (int)$currentUser['id'],
                            $commentId
                        );
                    }
                }
            } catch (\Exception $notifEx) {
                error_log('Comment notification error: ' . $notifEx->getMessage());
            }

            $comment = $this->commentModel->getComment($commentId);
            http_response_code(201);
            echo json_encode(['message' => 'Comment created', 'comment' => $comment]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create comment']);
        }
    }

    private function methodNotAllowed(): void
    {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}
