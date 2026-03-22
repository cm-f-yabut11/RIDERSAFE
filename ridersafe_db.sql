-- ============================================================
-- RiderSafe — Complete Database Setup
-- One file. Import this once in phpMyAdmin to set everything up.
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `ridersafe_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
USE `ridersafe_db`;

-- ── USERS ────────────────────────────────────────────────────
CREATE TABLE `users` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `uid`          varchar(20)           DEFAULT NULL,
  `fullname`     varchar(100) NOT NULL,
  `email`        varchar(100) NOT NULL,
  `password`     varchar(255) NOT NULL,
  `account_type` enum('rider','contact') NOT NULL,
  `theme`        varchar(50)           DEFAULT 'default',
  `button_label` varchar(50)           DEFAULT 'SAFE',
  `created_at`   timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── RIDER SETTINGS ───────────────────────────────────────────
CREATE TABLE `rider_settings` (
  `id`                 int(11)    NOT NULL AUTO_INCREMENT,
  `rider_id`           int(11)    NOT NULL,
  `ping_interval`      int(11)    NOT NULL DEFAULT 1800, -- stored as seconds (1800 = 30 minutes)
  `auto_grace_minutes` int(11)             DEFAULT 5,
  `system_active`      tinyint(1)          DEFAULT 0,
  `last_ping_time`     datetime            DEFAULT NULL,
  `trip_start_time`    datetime            DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rider_id` (`rider_id`),
  CONSTRAINT `rs_ibfk_1` FOREIGN KEY (`rider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── CONTACT LINKS ────────────────────────────────────────────
CREATE TABLE `contact_links` (
  `id`         int(11)   NOT NULL AUTO_INCREMENT,
  `rider_id`   int(11)   NOT NULL,
  `contact_id` int(11)   NOT NULL,
  `status`     enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_link` (`rider_id`, `contact_id`),
  CONSTRAINT `cl_ibfk_1` FOREIGN KEY (`rider_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cl_ibfk_2` FOREIGN KEY (`contact_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── PINGS ────────────────────────────────────────────────────
CREATE TABLE `pings` (
  `id`         int(11)         NOT NULL AUTO_INCREMENT,
  `rider_id`   int(11)         NOT NULL,
  `latitude`   decimal(10,8)            DEFAULT NULL,
  `longitude`  decimal(11,8)            DEFAULT NULL,
  `status`     enum('confirmed','missed','manual_request') NOT NULL,
  `created_at` timestamp       NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rider_id` (`rider_id`),
  CONSTRAINT `p_ibfk_1` FOREIGN KEY (`rider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── MANUAL PINGS ─────────────────────────────────────────────
CREATE TABLE `manual_pings` (
  `id`         int(11)   NOT NULL AUTO_INCREMENT,
  `rider_id`   int(11)   NOT NULL,
  `contact_id` int(11)   NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `mp_ibfk_1` FOREIGN KEY (`rider_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mp_ibfk_2` FOREIGN KEY (`contact_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── NOTIFICATIONS ────────────────────────────────────────────
CREATE TABLE `notifications` (
  `id`         int(11)   NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)   NOT NULL,
  `type`       enum('ping_confirmed','ping_missed','manual_ping') NOT NULL,
  `message`    text      NOT NULL,
  `is_read`    tinyint(1)         DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `n_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── BUTTON CUSTOMIZATION ─────────────────────────────────────
CREATE TABLE `button_customization` (
  `id`            int(11)     NOT NULL AUTO_INCREMENT,
  `rider_id`      int(11)     NOT NULL,
  `btn_label`     varchar(12) NOT NULL DEFAULT 'SAFE',
  `btn_color`     varchar(7)  NOT NULL DEFAULT '#2ecc8a',
  `btn_color2`    varchar(7)           DEFAULT NULL,
  `btn_gradient`  tinyint(1)  NOT NULL DEFAULT 0,
  `btn_size`      enum('small','medium','large') NOT NULL DEFAULT 'medium',
  `sound_enabled` tinyint(1)  NOT NULL DEFAULT 1,
  `press_effect`  enum('pulse','pop','shake','ripple','bounce','flash') NOT NULL DEFAULT 'pulse',
  `updated_at`    timestamp   NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rider` (`rider_id`),
  CONSTRAINT `bc_ibfk_1` FOREIGN KEY (`rider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── LOGIN LOGS ───────────────────────────────────────────────
CREATE TABLE `login_logs` (
  `id`         int(11)     NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)     NOT NULL,
  `ip_address` varchar(45)          DEFAULT NULL,
  `user_agent` text                 DEFAULT NULL,
  `created_at` timestamp   NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ll_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── SAMPLE DATA ──────────────────────────────────────────────
-- Default credentials — password: Password123
INSERT INTO `users` (`id`, `fullname`, `email`, `password`, `account_type`) VALUES
(1, 'Juan Dela Cruz',  'rider@ridersafe.com',   '$2y$10$PhpzNMJhFnVK8Di.nMJxWuJkqXXxuaW55ooTMJwpMmykeeJRRDEfK', 'rider'),
(2, 'Maria Dela Cruz', 'contact@ridersafe.com', '$2y$10$PhpzNMJhFnVK8Di.nMJxWuJkqXXxuaW55ooTMJwpMmykeeJRRDEfK', 'contact');

INSERT INTO `rider_settings` (`rider_id`, `ping_interval`, `auto_grace_minutes`, `system_active`) VALUES
(1, 1800, 5, 0); -- 1800 seconds = 30 minutes

INSERT INTO `contact_links` (`rider_id`, `contact_id`, `status`) VALUES
(1, 2, 'accepted');

COMMIT;
