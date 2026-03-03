<?php

namespace App\Controllers;

use App\Services\FileUploadService;
use App\Middleware\AuthMiddleware;

class UploadController
{
    private FileUploadService $uploadService;
    private AuthMiddleware $authMiddleware;

    public function __construct()
    {
        $this->uploadService = new FileUploadService();
        $this->authMiddleware = new AuthMiddleware();
    }

    public function handle(string $method, array $parts): void
    {
        if ($method !== 'POST') {
            $this->methodNotAllowed();
            return;
        }

        $type = $parts[0] ?? 'image';

        switch ($type) {
            case 'image':
                $this->uploadImage();
                break;

            case 'video':
                $this->uploadVideo();
                break;

            case 'avatar':
                $this->uploadAvatar();
                break;

            case 'cover':
                $this->uploadCover();
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid upload type']);
                break;
        }
    }

    private function uploadImage(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        if (empty($_FILES['image'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No image file provided']);
            return;
        }

        try {
            $result = $this->uploadService->uploadImage($_FILES['image'], 'posts');

            echo json_encode([
                'message' => 'Image uploaded successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function uploadVideo(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        if (empty($_FILES['video'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No video file provided']);
            return;
        }

        try {
            $result = $this->uploadService->uploadVideo($_FILES['video'], 'posts');

            echo json_encode([
                'message' => 'Video uploaded successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function uploadAvatar(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        if (empty($_FILES['avatar'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No avatar file provided']);
            return;
        }

        try {
            $result = $this->uploadService->uploadImage($_FILES['avatar'], 'avatars');

            // Update user avatar
            $userModel = new \App\Models\User();
            $userModel->updateProfile($currentUser['id'], [
                'avatar_url' => $result['url'],
            ]);

            echo json_encode([
                'message' => 'Avatar uploaded successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function uploadCover(): void
    {
        $currentUser = $this->authMiddleware->authenticate();
        if (!$currentUser) {
            return;
        }

        if (empty($_FILES['cover'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No cover photo file provided']);
            return;
        }

        try {
            $result = $this->uploadService->uploadImage($_FILES['cover'], 'covers');

            // Update user cover photo
            $userModel = new \App\Models\User();
            $userModel->updateProfile($currentUser['id'], [
                'cover_photo_url' => $result['url'],
            ]);

            echo json_encode([
                'message' => 'Cover photo uploaded successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function methodNotAllowed(): void
    {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}
