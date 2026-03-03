<?php

namespace App\Models;

class Post extends BaseModel
{
    protected string $table = 'posts';

    public function createPost(int $userId, string $content, array $media = []): int
    {
        $data = [
            'user_id' => $userId,
            'content' => $content,
            'media_type' => $media['type'] ?? 'none',
            'media_url' => $media['url'] ?? null,
            'media_thumbnail_url' => $media['thumbnail'] ?? null,
            'visibility' => $media['visibility'] ?? 'public',
        ];

        return $this->create($data);
    }

    public function getFeed(int $userId, int $limit = 20, int $offset = 0): array
    {
        // Get posts from followed users + own posts, ordered by recent with follow weight
        $sql = "
            SELECT DISTINCT p.*, u.username, u.avatar_url,
                   (CASE WHEN f.follower_id IS NOT NULL THEN 1 ELSE 0 END) as is_followed
            FROM posts p
            INNER JOIN users u ON p.user_id = u.id
            LEFT JOIN follows f ON p.user_id = f.following_id AND f.follower_id = ?
            WHERE (p.user_id = ? OR f.follower_id = ? OR p.visibility = 'public')
              AND p.is_deleted = 0
              AND u.is_banned = 0
            ORDER BY p.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->query($sql, [$userId, $userId, $userId]);
    }

    public function getUserPosts(int $userId, int $requesterId = null, int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT p.*, u.username, u.avatar_url
            FROM posts p
            INNER JOIN users u ON p.user_id = u.id
            WHERE p.user_id = ?
              AND p.is_deleted = 0
              AND u.is_banned = 0
        ";

        // Apply visibility filter
        if ($requesterId && $requesterId !== $userId) {
            $sql .= " AND (p.visibility = 'public' OR 
                          (p.visibility = 'followers' AND EXISTS (
                              SELECT 1 FROM follows WHERE follower_id = {$requesterId} AND following_id = {$userId}
                          )))";
        } elseif (!$requesterId) {
            $sql .= " AND p.visibility = 'public'";
        }

        $sql .= " ORDER BY p.created_at DESC LIMIT {$limit} OFFSET {$offset}";

        return $this->query($sql, [$userId]);
    }

    public function getPost(int $postId, int $userId = null): ?array
    {
        $sql = "
            SELECT p.*, u.username, u.avatar_url, u.id as author_id
            FROM posts p
            INNER JOIN users u ON p.user_id = u.id
            WHERE p.id = ? AND p.is_deleted = 0
            LIMIT 1
        ";

        $post = $this->queryOne($sql, [$postId]);

        if (!$post) {
            return null;
        }

        // Check visibility
        if ($post['visibility'] === 'followers' && $userId && $userId !== $post['author_id']) {
            $isFollowing = $this->queryOne(
                "SELECT id FROM follows WHERE follower_id = ? AND following_id = ?",
                [$userId, $post['author_id']]
            );

            if (!$isFollowing) {
                return null;
            }
        } elseif ($post['visibility'] === 'private' && (!$userId || $userId !== $post['author_id'])) {
            return null;
        }

        return $post;
    }

    public function deletePost(int $postId, int $userId): bool
    {
        return $this->execute(
            "UPDATE posts SET is_deleted = 1, deleted_at = NOW() WHERE id = ? AND user_id = ?",
            [$postId, $userId]
        );
    }

    public function likePost(int $postId, int $userId): bool
    {
        try {
            $this->execute(
                "INSERT INTO likes (post_id, user_id) VALUES (?, ?)",
                [$postId, $userId]
            );

            // Update like count
            $this->execute(
                "UPDATE posts SET like_count = like_count + 1 WHERE id = ?",
                [$postId]
            );

            return true;
        } catch (\PDOException $e) {
            return false; // Already liked
        }
    }

    public function unlikePost(int $postId, int $userId): bool
    {
        $result = $this->execute(
            "DELETE FROM likes WHERE post_id = ? AND user_id = ?",
            [$postId, $userId]
        );

        if ($result) {
            // Update like count
            $this->execute(
                "UPDATE posts SET like_count = GREATEST(like_count - 1, 0) WHERE id = ?",
                [$postId]
            );
        }

        return $result;
    }

    public function hasLiked(int $postId, int $userId): bool
    {
        $result = $this->queryOne(
            "SELECT id FROM likes WHERE post_id = ? AND user_id = ?",
            [$postId, $userId]
        );

        return $result !== null;
    }

    public function getLikes(int $postId, int $limit = 50): array
    {
        return $this->query(
            "SELECT u.id, u.username, u.avatar_url, l.created_at
             FROM likes l
             INNER JOIN users u ON l.user_id = u.id
             WHERE l.post_id = ?
             ORDER BY l.created_at DESC
             LIMIT {$limit}",
            [$postId]
        );
    }

    public function incrementCommentCount(int $postId): bool
    {
        return $this->execute(
            "UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?",
            [$postId]
        );
    }

    public function decrementCommentCount(int $postId): bool
    {
        return $this->execute(
            "UPDATE posts SET comment_count = GREATEST(comment_count - 1, 0) WHERE id = ?",
            [$postId]
        );
    }
}
