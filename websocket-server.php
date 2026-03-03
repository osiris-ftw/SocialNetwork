<?php

/**
 * WebSocket Server using Ratchet
 * Handles real-time messaging and notifications
 */

require_once __DIR__ . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Config\Config;
use App\Config\RedisClient;
use App\Services\AuthService;

class WebSocketServer implements MessageComponentInterface
{
    protected $clients;
    protected $userConnections;
    private $redis;
    private $authService;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        $this->userConnections = [];
        $this->redis = RedisClient::getInstance()->getClient();
        $this->authService = new AuthService();

        echo "WebSocket server initialized\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // Parse query parameters from URL
        $query = [];
        if (isset($conn->httpRequest)) {
            $queryString = $conn->httpRequest->getUri()->getQuery();
            parse_str($queryString, $query);
        }

        $token = $query['token'] ?? null;

        if (!$token) {
            echo "Connection rejected: No token provided\n";
            $conn->close();
            return;
        }

        $payload = $this->authService->verifyToken($token);

        if (!$payload || !isset($payload['user_id'])) {
            echo "Connection rejected: Invalid token\n";
            $conn->close();
            return;
        }

        $userId = $payload['user_id'];

        $this->clients->attach($conn);
        $conn->user_id = $userId;

        if (!isset($this->userConnections[$userId])) {
            $this->userConnections[$userId] = [];
        }
        $this->userConnections[$userId][] = $conn;

        echo "User {$userId} connected (Total connections: " . count($this->clients) . ")\n";

        // Send welcome message
        $conn->send(json_encode([
            'type' => 'connected',
            'message' => 'Connected to WebSocket server',
            'user_id' => $userId,
            'timestamp' => time(),
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);

        if (!$data || !isset($data['type'])) {
            $from->send(json_encode([
                'type' => 'error',
                'message' => 'Invalid message format',
            ]));
            return;
        }

        echo "Message from user {$from->user_id}: {$data['type']}\n";

        switch ($data['type']) {
            case 'message':
                $this->handleDirectMessage($from, $data);
                break;

            case 'typing':
                $this->handleTypingIndicator($from, $data);
                break;

            case 'ping':
                $from->send(json_encode([
                    'type' => 'pong',
                    'timestamp' => time(),
                ]));
                break;

            default:
                $from->send(json_encode([
                    'type' => 'error',
                    'message' => 'Unknown message type',
                ]));
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

        if (isset($conn->user_id)) {
            $userId = $conn->user_id;
            
            if (isset($this->userConnections[$userId])) {
                $this->userConnections[$userId] = array_filter(
                    $this->userConnections[$userId],
                    function ($c) use ($conn) {
                        return $c !== $conn;
                    }
                );

                if (empty($this->userConnections[$userId])) {
                    unset($this->userConnections[$userId]);
                }
            }

            echo "User {$userId} disconnected (Total connections: " . count($this->clients) . ")\n";
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }

    private function handleDirectMessage(ConnectionInterface $from, array $data)
    {
        if (!isset($data['recipient_id']) || !isset($data['content'])) {
            $from->send(json_encode([
                'type' => 'error',
                'message' => 'Missing recipient_id or content',
            ]));
            return;
        }

        $recipientId = $data['recipient_id'];

        // Send to recipient if online
        if (isset($this->userConnections[$recipientId])) {
            $message = [
                'type' => 'message',
                'sender_id' => $from->user_id,
                'content' => $data['content'],
                'message_id' => $data['message_id'] ?? null,
                'timestamp' => time(),
            ];

            foreach ($this->userConnections[$recipientId] as $conn) {
                $conn->send(json_encode($message));
            }

            echo "Message sent to user {$recipientId}\n";
        }

        // Confirm to sender
        $from->send(json_encode([
            'type' => 'message_sent',
            'recipient_id' => $recipientId,
            'message_id' => $data['message_id'] ?? null,
            'delivered' => isset($this->userConnections[$recipientId]),
            'timestamp' => time(),
        ]));
    }

    private function handleTypingIndicator(ConnectionInterface $from, array $data)
    {
        if (!isset($data['recipient_id'])) {
            return;
        }

        $recipientId = $data['recipient_id'];

        if (isset($this->userConnections[$recipientId])) {
            $message = [
                'type' => 'typing',
                'user_id' => $from->user_id,
                'is_typing' => $data['is_typing'] ?? true,
                'timestamp' => time(),
            ];

            foreach ($this->userConnections[$recipientId] as $conn) {
                $conn->send(json_encode($message));
            }
        }
    }

    public function subscribeToRedis()
    {
        // Subscribe to Redis pub/sub for notifications
        $pubsub = $this->redis->pubSubLoop();
        $pubsub->subscribe('notifications', 'messages');

        foreach ($pubsub as $message) {
            if ($message->kind === 'message') {
                $data = json_decode($message->payload, true);
                
                if ($data && isset($data['user_id'])) {
                    $userId = $data['user_id'];
                    
                    if (isset($this->userConnections[$userId])) {
                        foreach ($this->userConnections[$userId] as $conn) {
                            $conn->send(json_encode($data['data']));
                        }
                        
                        echo "Notification sent to user {$userId}\n";
                    }
                }
            }
        }
    }
}

// Start WebSocket server
$config = Config::getInstance();
$host = $config->get('websocket.host');
$port = $config->get('websocket.port');

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new WebSocketServer()
        )
    ),
    $port,
    $host
);

echo "WebSocket server running on {$host}:{$port}\n";
$server->run();
