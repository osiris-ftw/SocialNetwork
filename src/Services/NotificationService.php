<?php

namespace App\Services;

use App\Config\Config;
use App\Config\RedisClient;
use App\Models\Notification;
use Pusher\Pusher;

class NotificationService
{
    private Notification $notificationModel;
    private Config $config;
    private $redis;
    private bool $usePusher;
    private ?Pusher $pusher = null;

    public function __construct()
    {
        $this->notificationModel = new Notification();
        $this->config = Config::getInstance();
        $this->redis = RedisClient::getInstance()->getClient();
        $this->usePusher = $this->config->get('pusher.use_pusher');

        if ($this->usePusher) {
            $this->pusher = new Pusher(
                $this->config->get('pusher.key'),
                $this->config->get('pusher.secret'),
                $this->config->get('pusher.app_id'),
                ['cluster' => $this->config->get('pusher.cluster')]
            );
        }
    }

    public function notifyNewFollower(int $userId, int $followerId): void
    {
        $notificationId = $this->notificationModel->createNotification(
            $userId,
            $followerId,
            'new_follower',
            'user',
            $followerId
        );

        $this->pushNotification($userId, [
            'id' => $notificationId,
            'type' => 'new_follower',
            'actor_id' => $followerId,
            'message' => 'started following you',
        ]);
    }

    public function notifyNewLike(int $postOwnerId, int $likerId, int $postId): void
    {
        // Don't notify if user likes their own post
        if ($postOwnerId === $likerId) {
            return;
        }

        $notificationId = $this->notificationModel->createNotification(
            $postOwnerId,
            $likerId,
            'new_like',
            'post',
            $postId
        );

        $this->pushNotification($postOwnerId, [
            'id' => $notificationId,
            'type' => 'new_like',
            'actor_id' => $likerId,
            'entity_id' => $postId,
            'message' => 'liked your post',
        ]);
    }

    public function notifyNewComment(int $postOwnerId, int $commenterId, int $postId, int $commentId): void
    {
        // Don't notify if user comments on their own post
        if ($postOwnerId === $commenterId) {
            return;
        }

        $notificationId = $this->notificationModel->createNotification(
            $postOwnerId,
            $commenterId,
            'new_comment',
            'post',
            $postId
        );

        $this->pushNotification($postOwnerId, [
            'id' => $notificationId,
            'type' => 'new_comment',
            'actor_id' => $commenterId,
            'entity_id' => $postId,
            'comment_id' => $commentId,
            'message' => 'commented on your post',
        ]);
    }

    public function notifyCommentReply(int $originalCommenterId, int $replierId, int $commentId): void
    {
        if ($originalCommenterId === $replierId) {
            return;
        }

        $notificationId = $this->notificationModel->createNotification(
            $originalCommenterId,
            $replierId,
            'comment_reply',
            'comment',
            $commentId
        );

        $this->pushNotification($originalCommenterId, [
            'id' => $notificationId,
            'type' => 'comment_reply',
            'actor_id' => $replierId,
            'entity_id' => $commentId,
            'message' => 'replied to your comment',
        ]);
    }

    public function notifyNewMessage(int $recipientId, int $senderId, int $messageId): void
    {
        $notificationId = $this->notificationModel->createNotification(
            $recipientId,
            $senderId,
            'new_message',
            'message',
            $messageId
        );

        $this->pushNotification($recipientId, [
            'id' => $notificationId,
            'type' => 'new_message',
            'actor_id' => $senderId,
            'entity_id' => $messageId,
            'message' => 'sent you a message',
        ]);
    }

    private function pushNotification(int $userId, array $data): void
    {
        $data['timestamp'] = time();

        if ($this->usePusher && $this->pusher) {
            // Use Pusher
            $this->pusher->trigger("user-{$userId}", 'notification', $data);
        } else {
            // Use Redis pub/sub for Ratchet
            $this->redis->publish('notifications', json_encode([
                'user_id' => $userId,
                'data' => $data,
            ]));
        }
    }
}
