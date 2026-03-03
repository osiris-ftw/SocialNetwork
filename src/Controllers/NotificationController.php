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
        $subAction = $parts[1] ?? null;

        // Support POST /notifications/{id}/mark-read
        if ($action !== null && is_numeric($action) && $subAction === 'mark-read') {
            if ($method === 'POST') {
                $this->markOneAsRead((int)$action);
            } else {
                $this->methodNotAllowed();
            }
            return;
        }

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
            case 'unread-count':
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

        if (!empty($data['notification_ids']) && is_array($data['notification_ids'])) {
            foreach ($data['notification_ids'] as $nid) {
                $this->notificationModel->markAsRead((int)$nid, $currentUser['id']);
            }
        } elseif (!empty($data['notification_id'])) {
            $this->notificationModel->markAsRead((int)$data['notification_id'], $currentUser['id']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'notification_id or notification_ids required']);
            return;
        }

        echo json_encode(['message' => 'Notification(s) marked as read']);
    }

    private function markOneAsRead(int $notificationId): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $this->notificationModel->markAsRead($notificationId, $currentUser['id']);
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
