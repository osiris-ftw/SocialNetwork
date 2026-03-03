<?php

namespace App\Controllers;

use App\Models\Notification;
use App\Middleware\AuthMiddleware;

class NotificationController
{
    private Notification $notificationModel;
    private AuthMiddleware $authMiddleware;

    public function __construct()
    {
        $this->notificationModel = new Notification();
        $this->authMiddleware = new AuthMiddleware();
    }

    public function handle(string $method, array $parts): void
    {
        $action = $parts[0] ?? null;

        switch ($action) {
            case null:
            case 'list':
                if ($method === 'GET') {
                    $this->getNotifications();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'unread':
                if ($method === 'GET') {
                    $this->getUnreadNotifications();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'count':
                if ($method === 'GET') {
                    $this->getUnreadCount();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'mark-read':
                if ($method === 'POST') {
                    $this->markAsRead();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'mark-all-read':
                if ($method === 'POST') {
                    $this->markAllAsRead();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Notification endpoint not found']);
                break;
        }
    }

    private function getNotifications(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        $notifications = $this->notificationModel->getUserNotifications(
            $currentUser['id'],
            $limit,
            $offset
        );

        echo json_encode(['notifications' => $notifications]);
    }

    private function getUnreadNotifications(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $limit = (int)($_GET['limit'] ?? 50);
        $notifications = $this->notificationModel->getUnreadNotifications($currentUser['id'], $limit);

        echo json_encode(['notifications' => $notifications]);
    }

    private function getUnreadCount(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $count = $this->notificationModel->getUnreadCount($currentUser['id']);

        echo json_encode(['unread_count' => $count]);
    }

    private function markAsRead(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['notification_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Notification ID is required']);
            return;
        }

        $this->notificationModel->markAsRead($data['notification_id'], $currentUser['id']);

        echo json_encode(['message' => 'Notification marked as read']);
    }

    private function markAllAsRead(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $this->notificationModel->markAllAsRead($currentUser['id']);

        echo json_encode(['message' => 'All notifications marked as read']);
    }

    private function methodNotAllowed(): void
    {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}
