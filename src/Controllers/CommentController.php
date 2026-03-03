<?php

namespace App\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Event;
use App\Services\NotificationService;
use App\Middleware\AuthMiddleware;

class CommentController
{
    private Comment $commentModel;
    private Post $postModel;
    private Event $eventModel;
    private AuthMiddleware $authMiddleware;
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->commentModel = new Comment();
        $this->postModel = new Post();
        $this->eventModel = new Event();
        $this->authMiddleware = new AuthMiddleware();
        $this->notificationService = new NotificationService();
    }

    public function handle(string $method, array $parts): void
    {
        $commentId = $parts[0] ?? null;

        if (!$commentId) {
            if ($method === 'POST') {
                $this->createComment();
            } else {
                $this->methodNotAllowed();
            }
            return;
        }

        if ($method === 'GET') {
            $this->getComment($commentId);
        } elseif ($method === 'DELETE') {
            $this->deleteComment($commentId);
        } else {
            $this->methodNotAllowed();
        }
    }

    private function createComment(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['post_id']) || empty($data['content'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Post ID and content are required']);
            return;
        }

        try {
            $commentId = $this->commentModel->createComment(
                $data['post_id'],
                $currentUser['id'],
                $data['content'],
                $data['parent_comment_id'] ?? null
            );

            // Increment post comment count
            $this->postModel->incrementCommentCount($data['post_id']);

            // Log event
            $this->eventModel->logEvent(
                $currentUser['id'],
                'comment_created',
                'comment',
                $commentId
            );

            // Fire notifications (non-fatal)
            try {
                $post = $this->postModel->find((int)$data['post_id']);
                if ($post) {
                    if (!empty($data['parent_comment_id'])) {
                        // Reply to a comment — notify original commenter
                        $parentComment = $this->commentModel->find((int)$data['parent_comment_id']);
                        if ($parentComment && $parentComment['user_id'] != $currentUser['id']) {
                            $this->notificationService->notifyCommentReply(
                                (int)$parentComment['user_id'],
                                (int)$currentUser['id'],
                                $commentId
                            );
                        }
                    }
                    // Notify post owner (if different from commenter)
                    if ($post['user_id'] != $currentUser['id']) {
                        $this->notificationService->notifyNewComment(
                            (int)$post['user_id'],
                            (int)$currentUser['id'],
                            (int)$data['post_id'],
                            $commentId
                        );
                    }
                }
            } catch (\Exception $notifEx) {
                error_log('Comment notification error: ' . $notifEx->getMessage());
            }

            $comment = $this->commentModel->getComment($commentId);

            http_response_code(201);
            echo json_encode([
                'message' => 'Comment created successfully',
                'comment' => $comment,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create comment']);
        }
    }

    private function getComment(int $commentId): void
    {
        $comment = $this->commentModel->getComment($commentId);

        if (!$comment) {
            http_response_code(404);
            echo json_encode(['error' => 'Comment not found']);
            return;
        }

        echo json_encode(['comment' => $comment]);
    }

    private function deleteComment(int $commentId): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $comment = $this->commentModel->find($commentId);
        
        if (!$comment) {
            http_response_code(404);
            echo json_encode(['error' => 'Comment not found']);
            return;
        }

        if ($this->commentModel->deleteComment($commentId, $currentUser['id'])) {
            // Decrement post comment count
            $this->postModel->decrementCommentCount($comment['post_id']);

            echo json_encode(['message' => 'Comment deleted successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Failed to delete comment']);
        }
    }

    private function methodNotAllowed(): void
    {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}
