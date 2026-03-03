<?php

/**
 * Social Network REST API Entry Point
 * Handles routing for all API endpoints
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove /index.php if present
$path = str_replace('/index.php', '', $path);
$path = rtrim($path, '/');

try {
    // Route the request
    $parts = explode('/', trim($path, '/'));
    $endpoint = $parts[0] ?? '';

    switch ($endpoint) {
        case 'auth':
            require_once __DIR__ . '/../src/Controllers/AuthController.php';
            $controller = new \App\Controllers\AuthController();
            $controller->handle($method, array_slice($parts, 1));
            break;

        case 'users':
            require_once __DIR__ . '/../src/Controllers/UserController.php';
            $controller = new \App\Controllers\UserController();
            $controller->handle($method, array_slice($parts, 1));
            break;

        case 'posts':
            require_once __DIR__ . '/../src/Controllers/PostController.php';
            $controller = new \App\Controllers\PostController();
            $controller->handle($method, array_slice($parts, 1));
            break;

        case 'comments':
            require_once __DIR__ . '/../src/Controllers/CommentController.php';
            $controller = new \App\Controllers\CommentController();
            $controller->handle($method, array_slice($parts, 1));
            break;

        case 'messages':
            require_once __DIR__ . '/../src/Controllers/MessageController.php';
            $controller = new \App\Controllers\MessageController();
            $controller->handle($method, array_slice($parts, 1));
            break;

        case 'notifications':
            require_once __DIR__ . '/../src/Controllers/NotificationController.php';
            $controller = new \App\Controllers\NotificationController();
            $controller->handle($method, array_slice($parts, 1));
            break;

        case 'admin':
            require_once __DIR__ . '/../src/Controllers/AdminController.php';
            $controller = new \App\Controllers\AdminController();
            $controller->handle($method, array_slice($parts, 1));
            break;

        case 'upload':
            require_once __DIR__ . '/../src/Controllers/UploadController.php';
            $controller = new \App\Controllers\UploadController();
            $controller->handle($method, array_slice($parts, 1));
            break;

        case '':
        case 'health':
            echo json_encode([
                'status' => 'ok',
                'service' => 'Social Network API',
                'version' => '1.0.0',
                'timestamp' => time(),
            ]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
    ]);
}
