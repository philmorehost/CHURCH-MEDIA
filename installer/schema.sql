-- Church Media Management System — full schema
-- Applied once by the installer (Stage 2). Safe to re-run (IF NOT EXISTS everywhere).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `site_title` VARCHAR(255) NOT NULL DEFAULT 'Grace & Life Church',
  `site_tagline` VARCHAR(255) NULL,
  `logo_path` VARCHAR(255) NULL,
  `favicon_path` VARCHAR(255) NULL,
  `hero_tagline` VARCHAR(255) NULL,
  `hero_scripture` VARCHAR(255) NULL,
  `contact_email` VARCHAR(150) NULL,
  `contact_phone` VARCHAR(50) NULL,
  `address` VARCHAR(255) NULL,
  `service_times` TEXT NULL COMMENT 'JSON array of {label, time}',
  `facebook_url` VARCHAR(255) NULL,
  `instagram_url` VARCHAR(255) NULL,
  `youtube_url` VARCHAR(255) NULL,
  `tiktok_url` VARCHAR(255) NULL,
  `livestream_embed_url` VARCHAR(500) NULL,
  `livestream_is_live` TINYINT(1) NOT NULL DEFAULT 0,
  `giving_url` VARCHAR(500) NULL,
  `footer_about_text` TEXT NULL,
  `meta_description` VARCHAR(255) NULL,
  `license_key` VARCHAR(120) NULL,
  `timezone` VARCHAR(64) NOT NULL DEFAULT 'Africa/Lagos',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','media_team','editor') NOT NULL DEFAULT 'media_team',
  `is_suspended` TINYINT(1) NOT NULL DEFAULT 0,
  `notify_on_login` TINYINT(1) NOT NULL DEFAULT 1,
  `bio` TEXT NULL,
  `avatar` VARCHAR(255) NULL,
  `last_login_at` TIMESTAMP NULL,
  `last_login_ip` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `username_attempted` VARCHAR(100) NULL,
  `event_type` ENUM('failed_login','successful_login','blocked_attempt') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ip_event_time` (`ip_address`, `event_type`, `created_at`),
  INDEX `idx_user_event_time` (`username_attempted`, `event_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ip_rules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL UNIQUE,
  `type` ENUM('whitelist','blacklist') NOT NULL,
  `is_auto_whitelisted` TINYINT(1) NOT NULL DEFAULT 0,
  `successful_session_count` INT NOT NULL DEFAULT 0,
  `reason` VARCHAR(255) NULL,
  `expires_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `country_rules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `country_code` VARCHAR(2) NOT NULL UNIQUE,
  `country_name` VARCHAR(100) NOT NULL,
  `status` ENUM('whitelisted','not_specified','blacklisted') NOT NULL DEFAULT 'not_specified',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_posts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `slug` VARCHAR(160) NULL UNIQUE,
  `caption` TEXT NULL,
  `post_type` ENUM('single_image','carousel','vertical_reel') NOT NULL,
  `likes_count` INT NOT NULL DEFAULT 0,
  `views_count` INT NOT NULL DEFAULT 0,
  `saves_count` INT NOT NULL DEFAULT 0,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_published_created` (`is_published`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_post_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `media_post_id` INT NOT NULL,
  `type` ENUM('image','video') NOT NULL,
  `source` ENUM('upload','youtube') NOT NULL DEFAULT 'upload',
  `file_path` VARCHAR(500) NOT NULL,
  `thumbnail_path` VARCHAR(500) NULL,
  `alt_text` VARCHAR(255) NULL,
  `processing_status` ENUM('ready','pending','failed') NOT NULL DEFAULT 'ready',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`media_post_id`) REFERENCES `media_posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `post_saves` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `media_post_id` INT NOT NULL,
  `fingerprint_hash` VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_save_post_fingerprint` (`media_post_id`, `fingerprint_hash`),
  FOREIGN KEY (`media_post_id`) REFERENCES `media_posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `post_comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `media_post_id` INT NOT NULL,
  `name` VARCHAR(100) NULL,
  `message` TEXT NOT NULL,
  `fingerprint_hash` VARCHAR(64) NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`media_post_id`) REFERENCES `media_posts`(`id`) ON DELETE CASCADE,
  INDEX `idx_comment_post` (`media_post_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media_post_categories` (
  `media_post_id` INT NOT NULL,
  `media_category_id` INT NOT NULL,
  PRIMARY KEY (`media_post_id`, `media_category_id`),
  FOREIGN KEY (`media_post_id`) REFERENCES `media_posts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`media_category_id`) REFERENCES `media_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `post_likes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `media_post_id` INT NOT NULL,
  `fingerprint_hash` VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_post_fingerprint` (`media_post_id`, `fingerprint_hash`),
  FOREIGN KEY (`media_post_id`) REFERENCES `media_posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `post_views` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `media_post_id` INT NOT NULL,
  `fingerprint_hash` VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_post_fingerprint` (`media_post_id`, `fingerprint_hash`),
  FOREIGN KEY (`media_post_id`) REFERENCES `media_posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(220) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `cover_image` VARCHAR(255) NULL,
  `start_at` DATETIME NOT NULL,
  `end_at` DATETIME NULL,
  `location` VARCHAR(255) NULL,
  `rsvp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `rsvp_url` VARCHAR(500) NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_published_start` (`is_published`, `start_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sermons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(220) NOT NULL UNIQUE,
  `speaker` VARCHAR(150) NULL,
  `series` VARCHAR(150) NULL,
  `scripture_ref` VARCHAR(150) NULL,
  `description` TEXT NULL,
  `audio_path` VARCHAR(255) NULL,
  `video_embed_url` VARCHAR(500) NULL,
  `cover_image` VARCHAR(255) NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `published_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_published_at` (`is_published`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `team_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `role_title` VARCHAR(150) NULL,
  `photo` VARCHAR(255) NULL,
  `bio` TEXT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `prayer_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NULL,
  `email` VARCHAR(150) NULL,
  `message` TEXT NOT NULL,
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('new','prayed','archived') NOT NULL DEFAULT 'new',
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed a starter set of media categories so the admin composer isn't empty on first login.
INSERT INTO `media_categories` (`name`, `slug`) VALUES
  ('Worship', 'worship'),
  ('Sermon Clip', 'sermon-clip'),
  ('Youth', 'youth'),
  ('Testimony', 'testimony'),
  ('Events', 'events'),
  ('Behind the Scenes', 'behind-the-scenes')
ON DUPLICATE KEY UPDATE `name` = `name`;

-- In-place migration guard for databases created before these columns/tables
-- existed. Each block checks the schema first so re-running the file is safe.
SET @has_source = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'media_post_items' AND COLUMN_NAME = 'source');
SET @mig_source = IF(@has_source = 0, 'ALTER TABLE `media_post_items` ADD COLUMN `source` ENUM(''upload'',''youtube'') NOT NULL DEFAULT ''upload'' AFTER `type`', 'SELECT 1');
PREPARE mig_source FROM @mig_source;
EXECUTE mig_source;
DEALLOCATE PREPARE mig_source;

SET @has_saves = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'media_posts' AND COLUMN_NAME = 'saves_count');
SET @mig_saves = IF(@has_saves = 0, 'ALTER TABLE `media_posts` ADD COLUMN `saves_count` INT NOT NULL DEFAULT 0 AFTER `views_count`', 'SELECT 1');
PREPARE mig_saves FROM @mig_saves;
EXECUTE mig_saves;
DEALLOCATE PREPARE mig_saves;
