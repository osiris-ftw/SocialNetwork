<?php

namespace App\Services;

use App\Config\Config;
use Intervention\Image\ImageManagerStatic as Image;

class FileUploadService
{
    private Config $config;
    private string $uploadPath;
    private int $maxSize;
    private array $allowedImageTypes;
    private array $allowedVideoTypes;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->uploadPath = $this->config->get('upload.path');
        $this->maxSize = $this->config->get('upload.max_size');
        $this->allowedImageTypes = $this->config->get('upload.allowed_image_types');
        $this->allowedVideoTypes = $this->config->get('upload.allowed_video_types');

        // Ensure upload directory exists
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    public function uploadImage(array $file, string $subdirectory = 'images'): array
    {
        $this->validateFile($file);
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $this->allowedImageTypes)) {
            throw new \Exception('Invalid image type. Allowed: ' . implode(', ', $this->allowedImageTypes));
        }

        // Validate image mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes)) {
            throw new \Exception('Invalid image mime type');
        }

        // Generate safe filename
        $filename = $this->generateSafeFilename($extension);
        $targetDir = $this->uploadPath . '/' . $subdirectory;
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $filename;

        // Process and resize image
        try {
            $image = Image::make($file['tmp_name']);
            
            // Resize if too large (max 2000px width)
            if ($image->width() > 2000) {
                $image->resize(2000, null, function ($constraint) {
                    $constraint->aspectRatio();
                });
            }

            // Strip exif data for privacy
            $image->save($targetPath, 85);

            // Generate thumbnail
            $thumbnailFilename = 'thumb_' . $filename;
            $thumbnailPath = $targetDir . '/' . $thumbnailFilename;
            
            $thumbnail = Image::make($file['tmp_name']);
            $thumbnail->fit(300, 300);
            $thumbnail->save($thumbnailPath, 80);

            return [
                'url' => "/uploads/{$subdirectory}/{$filename}",
                'thumbnail_url' => "/uploads/{$subdirectory}/{$thumbnailFilename}",
                'filename' => $filename,
                'size' => filesize($targetPath),
                'type' => 'image',
            ];
        } catch (\Exception $e) {
            throw new \Exception('Failed to process image: ' . $e->getMessage());
        }
    }

    public function uploadVideo(array $file, string $subdirectory = 'videos'): array
    {
        $this->validateFile($file);
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $this->allowedVideoTypes)) {
            throw new \Exception('Invalid video type. Allowed: ' . implode(', ', $this->allowedVideoTypes));
        }

        // Validate video mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['video/mp4', 'video/webm', 'video/quicktime'];
        if (!in_array($mimeType, $allowedMimes)) {
            throw new \Exception('Invalid video mime type');
        }

        // Generate safe filename
        $filename = $this->generateSafeFilename($extension);
        $targetDir = $this->uploadPath . '/' . $subdirectory;
        
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \Exception('Failed to upload video');
        }

        return [
            'url' => "/uploads/{$subdirectory}/{$filename}",
            'thumbnail_url' => null, // Could generate with ffmpeg
            'filename' => $filename,
            'size' => filesize($targetPath),
            'type' => 'video',
        ];
    }

    private function validateFile(array $file): void
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \Exception('Invalid file upload');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('File upload error: ' . $file['error']);
        }

        if ($file['size'] > $this->maxSize) {
            $maxMB = round($this->maxSize / 1048576, 2);
            throw new \Exception("File too large. Maximum size: {$maxMB}MB");
        }
    }

    private function generateSafeFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '_' . time() . '.' . $extension;
    }

    public function deleteFile(string $path): bool
    {
        $fullPath = $this->uploadPath . $path;
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }
}
