<?php

namespace App\Controllers;

use App\Models\Message;
use App\Models\Event;
use App\Middleware\AuthMiddleware;

class MessageController
{
    private Message $messageModel;
    private Event $eventModel;
    private AuthMiddleware $authMiddleware;

    public function __construct()
    {
        $this->messageModel = new Message();
        $this->eventModel = new Event();
        $this->authMiddleware = new AuthMiddleware();
    }

    public function handle(string $method, array $parts): void
    {
        $action = $parts[0] ?? null;

        switch ($action) {
            case 'conversations':
                if ($method === 'GET') {
                    $this->getConversations();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'conversation':
                if ($method === 'GET') {
                    $this->getConversation();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'send':
                if ($method === 'POST') {
                    $this->sendMessage();
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

            case 'unread-count':
                if ($method === 'GET') {
                    $this->getUnreadCount();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Message endpoint not found']);
                break;
        }
    }

    private function getConversations(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $limit = (int)($_GET['limit'] ?? 20);
        $conversations = $this->messageModel->getConversationsList($currentUser['id'], $limit);

        echo json_encode(['conversations' => $conversations]);
    }

    private function getConversation(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $otherUserId = (int)($_GET['user_id'] ?? 0);
        if (!$otherUserId) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            return;
        }

        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        $messages = $this->messageModel->getConversation(
            $currentUser['id'],
            $otherUserId,
            $limit,
            $offset
        );

        echo json_encode(['messages' => $messages]);
    }

    private function sendMessage(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['recipient_id']) || empty($data['content'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Recipient ID and content are required']);
            return;
        }

        try {
            $messageId = $this->messageModel->sendMessage(
                $currentUser['id'],
                $data['recipient_id'],
                $data['content']
            );

            // Log event
            $this->eventModel->logEvent(
                $currentUser['id'],
                'message_sent',
                'message',
                $messageId
            );

            http_response_code(201);
            echo json_encode([
                'message' => 'Message sent successfully',
                'message_id' => $messageId,
            ]);
        } catch (\Exception $e) {
            error_log("Message send error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to send message',
                'details' => $e->getMessage()
            ]);
        }
    }

    private function markAsRead(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (isset($data['message_id'])) {
            $this->messageModel->markAsRead($data['message_id'], $currentUser['id']);
        } elseif (isset($data['user_id'])) {
            $this->messageModel->markConversationAsRead($currentUser['id'], $data['user_id']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Message ID or User ID is required']);
            return;
        }

        echo json_encode(['message' => 'Marked as read']);
    }

    private function getUnreadCount(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        $count = $this->messageModel->getUnreadCount($currentUser['id']);

        echo json_encode(['unread_count' => $count]);
    }

    private function methodNotAllowed(): void
    {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}
