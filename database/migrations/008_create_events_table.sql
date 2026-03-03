-- Migration: 008_create_events_table.sql
-- Description: Create events table for analytics and activity tracking

CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    event_type ENUM('post_created', 'post_liked', 'comment_created', 'message_sent', 'user_followed', 'user_signup', 'user_login') NOT NULL,
    entity_type ENUM('post', 'comment', 'message', 'user', 'none') DEFAULT 'none',
    entity_id INT UNSIGNED NULL,
    metadata JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at),
    INDEX idx_analytics (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
