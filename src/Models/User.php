<?php

namespace App\Models;

use App\Config\Config;

class User extends BaseModel
{
    protected string $table = 'users';

    public function findByEmail(string $email): ?array
    {
        return $this->queryOne(
            "SELECT * FROM {$this->table} WHERE email = ? LIMIT 1",
            [$email]
        );
    }

    public function findByUsername(string $username): ?array
    {
        return $this->queryOne(
            "SELECT * FROM {$this->table} WHERE username = ? LIMIT 1",
            [$username]
        );
    }

    public function createUser(array $data): int
    {
        $passwordHash = $this->hashPassword($data['password']);
        $verificationToken = bin2hex(random_bytes(32));

        return $this->create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => $passwordHash,
            'email_verification_token' => $verificationToken,
            'bio' => $data['bio'] ?? null,
            'location' => $data['location'] ?? null,
            'website' => $data['website'] ?? null,
        ]);
    }

    public function verifyEmail(string $token): bool
    {
        $user = $this->queryOne(
            "SELECT id FROM {$this->table} WHERE email_verification_token = ? LIMIT 1",
            [$token]
        );

        if (!$user) {
            return false;
        }

        return $this->execute(
            "UPDATE {$this->table} SET email_verified_at = NOW(), email_verification_token = NULL WHERE id = ?",
            [$user['id']]
        );
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function hashPassword(string $password): string
    {
        $config = Config::getInstance();
        $algo = $config->get('security.password_hash_algo');

        if ($algo === 'argon2id' && defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        } elseif ($algo === 'argon2i' && defined('PASSWORD_ARGON2I')) {
            return password_hash($password, PASSWORD_ARGON2I);
        } else {
            $cost = $config->get('security.bcrypt_rounds', 12);
            return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
        }
    }

    public function updateProfile(int $userId, array $data): bool
    {
        $allowed = ['bio', 'avatar_url', 'cover_photo_url', 'location', 'website'];
        $updateData = array_intersect_key($data, array_flip($allowed));

        if (empty($updateData)) {
            return false;
        }

        return $this->update($userId, $updateData);
    }

    public function banUser(int $userId, string $reason): bool
    {
        return $this->update($userId, [
            'is_banned' => true,
            'banned_at' => date('Y-m-d H:i:s'),
            'banned_reason' => $reason,
        ]);
    }

    public function unbanUser(int $userId): bool
    {
        return $this->update($userId, [
            'is_banned' => false,
            'banned_at' => null,
            'banned_reason' => null,
        ]);
    }

    public function getFollowers(int $userId, int $limit = 50, int $offset = 0, ?int $currentUserId = null): array
    {
        if ($currentUserId) {
            return $this->query(
                "SELECT u.id, u.username, u.bio, u.avatar_url,
                        EXISTS(SELECT 1 FROM follows WHERE follower_id = ? AND following_id = u.id) as is_following
                 FROM {$this->table} u
                 INNER JOIN follows f ON f.follower_id = u.id
                 WHERE f.following_id = ?
                 ORDER BY f.created_at DESC
                 LIMIT {$limit} OFFSET {$offset}",
                [$currentUserId, $userId]
            );
        }
        return $this->query(
            "SELECT u.id, u.username, u.bio, u.avatar_url, 0 as is_following
             FROM {$this->table} u
             INNER JOIN follows f ON f.follower_id = u.id
             WHERE f.following_id = ?
             ORDER BY f.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            [$userId]
        );
    }

    public function getFollowing(int $userId, int $limit = 50, int $offset = 0, ?int $currentUserId = null): array
    {
        if ($currentUserId) {
            return $this->query(
                "SELECT u.id, u.username, u.bio, u.avatar_url,
                        EXISTS(SELECT 1 FROM follows WHERE follower_id = ? AND following_id = u.id) as is_following
                 FROM {$this->table} u
                 INNER JOIN follows f ON f.following_id = u.id
                 WHERE f.follower_id = ?
                 ORDER BY f.created_at DESC
                 LIMIT {$limit} OFFSET {$offset}",
                [$currentUserId, $userId]
            );
        }
        return $this->query(
            "SELECT u.id, u.username, u.bio, u.avatar_url, 0 as is_following
             FROM {$this->table} u
             INNER JOIN follows f ON f.following_id = u.id
             WHERE f.follower_id = ?
             ORDER BY f.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            [$userId]
        );
    }

    public function getMutualFollows(int $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->query(
            "SELECT u.id, u.username, u.bio, u.avatar_url
             FROM {$this->table} u
             INNER JOIN follows f1 ON f1.following_id = u.id AND f1.follower_id = ?
             INNER JOIN follows f2 ON f2.follower_id = u.id AND f2.following_id = ?
             WHERE u.id != ?
             ORDER BY u.username ASC
             LIMIT {$limit} OFFSET {$offset}",
            [$userId, $userId, $userId]
        );
    }

    public function isFollowing(int $followerId, int $followingId): bool
    {
        $result = $this->queryOne(
            "SELECT id FROM follows WHERE follower_id = ? AND following_id = ?",
            [$followerId, $followingId]
        );

        return $result !== null;
    }

    public function follow(int $followerId, int $followingId): bool
    {
        try {
            $this->execute(
                "INSERT INTO follows (follower_id, following_id) VALUES (?, ?)",
                [$followerId, $followingId]
            );
            return true;
        } catch (\PDOException $e) {
            return false; // Already following or other error
        }
    }

    public function unfollow(int $followerId, int $followingId): bool
    {
        return $this->execute(
            "DELETE FROM follows WHERE follower_id = ? AND following_id = ?",
            [$followerId, $followingId]
        );
    }

    public function search(string $query, int $limit = 20, int $offset = 0, ?int $currentUserId = null): array
    {
        $like = '%' . $query . '%';
        $params = [$like, $like];

        if ($currentUserId) {
            $sql = "SELECT u.id, u.username, u.bio, u.avatar_url,
                       EXISTS(SELECT 1 FROM follows WHERE follower_id = ? AND following_id = u.id) as is_following,
                       EXISTS(SELECT 1 FROM follows WHERE follower_id = u.id AND following_id = ?) as is_followed_back
                    FROM {$this->table} u
                    WHERE (u.username LIKE ? OR u.bio LIKE ?)
                      AND u.id != ?
                      AND u.is_banned = 0
                    ORDER BY u.username ASC
                    LIMIT {$limit} OFFSET {$offset}";
            $params = [$currentUserId, $currentUserId, $like, $like, $currentUserId];
        } else {
            $sql = "SELECT u.id, u.username, u.bio, u.avatar_url,
                       0 as is_following, 0 as is_followed_back
                    FROM {$this->table} u
                    WHERE (u.username LIKE ? OR u.bio LIKE ?)
                      AND u.is_banned = 0
                    ORDER BY u.username ASC
                    LIMIT {$limit} OFFSET {$offset}";
        }

        return $this->query($sql, $params);
    }

    public function getStats(int $userId): array
    {
        $stats = $this->queryOne(
            "SELECT
                (SELECT COUNT(*) FROM follows WHERE follower_id = ?) as following_count,
                (SELECT COUNT(*) FROM follows WHERE following_id = ?) as followers_count,
                (SELECT COUNT(*) FROM posts WHERE user_id = ? AND is_deleted = 0) as posts_count
            ",
            [$userId, $userId, $userId]
        );

        return $stats ?: ['following_count' => 0, 'followers_count' => 0, 'posts_count' => 0];
    }
}
