<?php

/**
 * Queue Worker
 * Processes background jobs from Redis queue
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\QueueService;
use App\Services\EmailService;
use App\Services\NotificationService;

$queueService = new QueueService();
$emailService = new EmailService();
$notificationService = new NotificationService();

echo "Queue worker started...\n";

// Process jobs continuously
while (true) {
    try {
        $job = $queueService->pop();

        if (!$job) {
            // No jobs, sleep for a bit
            sleep(1);
            continue;
        }

        echo "Processing job: {$job['job']}\n";

        switch ($job['job']) {
            case 'send_verification_email':
                $emailService->sendVerificationEmail(
                    $job['data']['email'],
                    $job['data']['username'],
                    $job['data']['token']
                );
                echo "Sent verification email to {$job['data']['email']}\n";
                break;

            case 'send_notification':
                // Handle different notification types
                $type = $job['data']['type'];
                $userId = $job['data']['user_id'];
                $data = $job['data']['data'];

                switch ($type) {
                    case 'new_follower':
                        $notificationService->notifyNewFollower(
                            $userId,
                            $data['follower_id']
                        );
                        break;

                    case 'new_like':
                        $notificationService->notifyNewLike(
                            $userId,
                            $data['liker_id'],
                            $data['post_id']
                        );
                        break;

                    case 'new_comment':
                        $notificationService->notifyNewComment(
                            $userId,
                            $data['commenter_id'],
                            $data['post_id'],
                            $data['comment_id']
                        );
                        break;

                    case 'new_message':
                        $notificationService->notifyNewMessage(
                            $userId,
                            $data['sender_id'],
                            $data['message_id']
                        );
                        break;
                }

                echo "Sent {$type} notification to user {$userId}\n";
                break;

            case 'process_media':
                // Placeholder for media processing (e.g., thumbnail generation, video encoding)
                echo "Processing media: {$job['data']['file_path']}\n";
                // Add your media processing logic here
                break;

            default:
                echo "Unknown job type: {$job['job']}\n";
                break;
        }
    } catch (\Exception $e) {
        echo "Error processing job: " . $e->getMessage() . "\n";
    }
}
