<?php

namespace App\Models;

class Comment extends BaseModel
{
    protected string $table = 'comments';

    public function createComment(int $postId, int $userId, string $content, ?int $parentCommentId = null): int
    {
        return $this->create([
            'post_id' => $postId,
            'user_id' => $userId,
            'parent_comment_id' => $parentCommentId,
            'content' => $content,
        ]);
    }

    public function getPostComments(int $postId, int $limit = 50, int $offset = 0): array
    {
        // Get top-level comments
        $topLevelComments = $this->query(
            "SELECT c.*, u.username, u.avatar_url
             FROM comments c
             INNER JOIN users u ON c.user_id = u.id
             WHERE c.post_id = ? AND c.parent_comment_id IS NULL AND c.is_deleted = 0
             ORDER BY c.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            [$postId]
        );

        // Get replies for each top-level comment
        foreach ($topLevelComments as &$comment) {
            $comment['replies'] = $this->query(
                "SELECT c.*, u.username, u.avatar_url
                 FROM comments c
                 INNER JOIN users u ON c.user_id = u.id
                 WHERE c.parent_comment_id = ? AND c.is_deleted = 0
                 ORDER BY c.created_at ASC",
                [$comment['id']]
            );
        }

        return $topLevelComments;
    }

    public function getComment(int $commentId): ?array
    {
        return $this->queryOne(
            "SELECT c.*, u.username, u.avatar_url
             FROM comments c
             INNER JOIN users u ON c.user_id = u.id
             WHERE c.id = ? AND c.is_deleted = 0
             LIMIT 1",
            [$commentId]
        );
    }

    public function deleteComment(int $commentId, int $userId): bool
    {
        return $this->execute(
            "UPDATE comments SET is_deleted = 1, deleted_at = NOW() 
             WHERE id = ? AND user_id = ?",
            [$commentId, $userId]
        );
    }

    public function getUserComments(int $userId, int $limit = 20, int $offset = 0): array
    {
        return $this->query(
            "SELECT c.*, u.username, u.avatar_url, p.content as post_content
             FROM comments c
             INNER JOIN users u ON c.user_id = u.id
             INNER JOIN posts p ON c.post_id = p.id
             WHERE c.user_id = ? AND c.is_deleted = 0
             ORDER BY c.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            [$userId]
        );
    }
}
