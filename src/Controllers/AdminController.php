<?php

namespace App\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Event;
use App\Middleware\AuthMiddleware;

class AdminController
{
    private Report $reportModel;
    private User $userModel;
    private Post $postModel;
    private Comment $commentModel;
    private Event $eventModel;
    private AuthMiddleware $authMiddleware;

    public function __construct()
    {
        $this->reportModel = new Report();
        $this->userModel = new User();
        $this->postModel = new Post();
        $this->commentModel = new Comment();
        $this->eventModel = new Event();
        $this->authMiddleware = new AuthMiddleware();
    }

    public function handle(string $method, array $parts): void
    {
        // Authenticate and require admin
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        if (!$this->authMiddleware->requireAdmin($currentUser)) {
            return;
        }

        $action = $parts[0] ?? null;

        switch ($action) {
            case 'reports':
                if ($method === 'GET') {
                    $this->getReports();
                } elseif ($method === 'POST') {
                    $this->createReport();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'report':
                $reportId = $parts[1] ?? null;
                if (!$reportId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Report ID required']);
                    return;
                }

                if ($method === 'PUT') {
                    $this->updateReport($reportId, $currentUser['id']);
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'ban-user':
                if ($method === 'POST') {
                    $this->banUser();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'unban-user':
                if ($method === 'POST') {
                    $this->unbanUser();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'delete-post':
                if ($method === 'DELETE') {
                    $this->deletePost();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'delete-comment':
                if ($method === 'DELETE') {
                    $this->deleteComment();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'stats':
                if ($method === 'GET') {
                    $this->getStats();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'analytics':
                if ($method === 'GET') {
                    $this->getAnalytics();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Admin endpoint not found']);
                break;
        }
    }

    private function getReports(): void
    {
        $status = $_GET['status'] ?? null;
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        $reports = $this->reportModel->getReports($status, $limit, $offset);

        echo json_encode(['reports' => $reports]);
    }

    private function createReport(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['entity_type']) || empty($data['entity_id']) || empty($data['reason'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Entity type, entity ID, and reason are required']);
            return;
        }

        $currentUser = $this->authMiddleware->authenticate();

        try {
            $reportId = $this->reportModel->createReport(
                $currentUser['id'],
                $data['entity_type'],
                $data['entity_id'],
                $data['reason'],
                $data['description'] ?? null
            );

            http_response_code(201);
            echo json_encode([
                'message' => 'Report created successfully',
                'report_id' => $reportId,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create report']);
        }
    }

    private function updateReport(int $reportId, int $reviewerId): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Status is required']);
            return;
        }

        if ($this->reportModel->updateReportStatus(
            $reportId,
            $reviewerId,
            $data['status'],
            $data['resolution_notes'] ?? null
        )) {
            echo json_encode(['message' => 'Report updated successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Failed to update report']);
        }
    }

    private function banUser(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['user_id']) || empty($data['reason'])) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID and reason are required']);
            return;
        }

        if ($this->userModel->banUser($data['user_id'], $data['reason'])) {
            echo json_encode(['message' => 'User banned successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Failed to ban user']);
        }
    }

    private function unbanUser(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['user_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            return;
        }

        if ($this->userModel->unbanUser($data['user_id'])) {
            echo json_encode(['message' => 'User unbanned successfully']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Failed to unban user']);
        }
    }

    private function deletePost(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['post_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Post ID is required']);
            return;
        }

        // Admin can delete any post
        $post = $this->postModel->find($data['post_id']);
        if ($post) {
            $this->postModel->update($data['post_id'], [
                'is_deleted' => true,
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);
            echo json_encode(['message' => 'Post deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Post not found']);
        }
    }

    private function deleteComment(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['comment_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Comment ID is required']);
            return;
        }

        // Admin can delete any comment
        $comment = $this->commentModel->find($data['comment_id']);
        if ($comment) {
            $this->commentModel->update($data['comment_id'], [
                'is_deleted' => true,
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);
            echo json_encode(['message' => 'Comment deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Comment not found']);
        }
    }

    private function getStats(): void
    {
        $reportStats = $this->reportModel->getReportStats();

        echo json_encode([
            'reports' => $reportStats,
        ]);
    }

    private function getAnalytics(): void
    {
        $days = (int)($_GET['days'] ?? 30);
        $dailyStats = $this->eventModel->getDailyStats($days);

        echo json_encode([
            'daily_stats' => $dailyStats,
        ]);
    }

    private function methodNotAllowed(): void
    {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}
