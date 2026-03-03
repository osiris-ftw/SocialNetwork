-- Migration: 007_create_notifications_table.sql
-- Description: Create notifications table for in-app notifications

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NULL,
    type ENUM('new_follower', 'new_comment', 'new_like', 'new_message', 'comment_reply') NOT NULL,
    entity_type ENUM('post', 'comment', 'message', 'user') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    content TEXT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_actor_id (actor_id),
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
