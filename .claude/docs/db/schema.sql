-- Fluent Events — Database Schema (MySQL, DrawSQL/Workbench import)
-- 9 plugin tables (wp_fevents_*) + WP core stubs (wp_users, wp_posts). Generated — do not hand-edit.
-- Ticketing: event -> ticket_types -> registrations (buyer+payment) -> guests (1 per ticket).
-- Roster: people (persons) + sponsors (orgs). Media/identity = WP core (wp_posts/wp_users).

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `wp_users` (
  `ID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_login` VARCHAR(60) NOT NULL,
  `user_email` VARCHAR(100) NOT NULL,
  `display_name` VARCHAR(250) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `wp_posts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `wp_fevents_meta` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `object_type` VARCHAR(64) NOT NULL,
  `object_id` BIGINT UNSIGNED NULL,
  `meta_key` VARCHAR(255) NOT NULL,
  `value` LONGTEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_meta_lookup` (`object_type`, `object_id`, `meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `wp_fevents_calendars` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `logo_media_id` BIGINT UNSIGNED NULL,
  `cover_media_id` BIGINT UNSIGNED NULL,
  `visibility` VARCHAR(64) NOT NULL,
  `settings` JSON NULL,
  `status` VARCHAR(64) NOT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_calendars_slug` (`slug`),
  KEY `idx_calendar_visibility` (`visibility`),
  KEY `idx_calendar_status` (`status`),
  CONSTRAINT `fk_logo_media_id_calendars` FOREIGN KEY (`logo_media_id`) REFERENCES `wp_posts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cover_media_id_calendars` FOREIGN KEY (`cover_media_id`) REFERENCES `wp_posts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_created_by_calendars` FOREIGN KEY (`created_by`) REFERENCES `wp_users` (`ID`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `wp_fevents_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` LONGTEXT NULL,
  `status` VARCHAR(64) NOT NULL,
  `visibility` VARCHAR(64) NOT NULL,
  `start_at` DATETIME NOT NULL,
  `end_at` DATETIME NOT NULL,
  `timezone` VARCHAR(64) NOT NULL,
  `calendar_id` BIGINT UNSIGNED NOT NULL,
  `parent_event_id` BIGINT UNSIGNED NULL,
  `venue_type` VARCHAR(64) NOT NULL,
  `address` VARCHAR(255) NULL,
  `lat` DECIMAL(10,8) NULL,
  `lon` DECIMAL(11,8) NULL,
  `online_meeting_url` VARCHAR(255) NULL,
  `join_policy` VARCHAR(64) NOT NULL,
  `waitlist_enabled` TINYINT(1) NOT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `cover_image_id` BIGINT UNSIGNED NULL,
  `currency` VARCHAR(3) NULL,
  `form_fields` JSON NULL,
  `settings` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_events_slug` (`slug`),
  KEY `idx_event_state` (`status`, `visibility`),
  KEY `idx_event_window` (`start_at`, `end_at`),
  CONSTRAINT `fk_calendar_id_events` FOREIGN KEY (`calendar_id`) REFERENCES `wp_fevents_calendars` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_parent_event_id_events` FOREIGN KEY (`parent_event_id`) REFERENCES `wp_fevents_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_created_by_events` FOREIGN KEY (`created_by`) REFERENCES `wp_users` (`ID`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cover_image_id_events` FOREIGN KEY (`cover_image_id`) REFERENCES `wp_posts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `wp_fevents_ticket_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `capacity` INT NULL,
  `sold` INT NOT NULL,
  `min_per_reg` INT NOT NULL,
  `max_per_reg` INT NULL,
  `sale_start_at` DATETIME NULL,
  `sale_end_at` DATETIME NULL,
  `status` VARCHAR(64) NOT NULL,
  `sort_order` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_event_status` (`event_id`, `status`),
  CONSTRAINT `fk_event_id_types` FOREIGN KEY (`event_id`) REFERENCES `wp_fevents_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `wp_fevents_registrations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `buyer_name` VARCHAR(255) NOT NULL,
  `buyer_email` VARCHAR(192) NOT NULL,
  `buyer_phone` VARCHAR(16) NULL,
  `tickets` INT NOT NULL,
  `status` VARCHAR(64) NOT NULL,
  `payment_status` VARCHAR(64) NOT NULL,
  `payment_ref` VARCHAR(255) NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `paid_at` DATETIME NULL,
  `source` VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reg_event_status` (`event_id`, `status`),
  KEY `idx_reg_email` (`buyer_email`),
  CONSTRAINT `fk_event_id_registrations` FOREIGN KEY (`event_id`) REFERENCES `wp_fevents_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `wp_fevents_guests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `registration_id` BIGINT UNSIGNED NOT NULL,
  `ticket_type_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NULL,
  `email` VARCHAR(192) NULL,
  `phone` VARCHAR(16) NULL,
  `status` VARCHAR(64) NOT NULL,
  `form_response` JSON NULL,
  `ticket_code` VARCHAR(64) NULL,
  `checked_in_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_guests_ticket_code` (`ticket_code`),
  KEY `idx_guest_event_status` (`event_id`, `status`),
  CONSTRAINT `fk_event_id_guests` FOREIGN KEY (`event_id`) REFERENCES `wp_fevents_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_registration_id_guests` FOREIGN KEY (`registration_id`) REFERENCES `wp_fevents_registrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_type_id_guests` FOREIGN KEY (`ticket_type_id`) REFERENCES `wp_fevents_ticket_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `wp_fevents_sponsors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `logo_id` BIGINT UNSIGNED NULL,
  `website` VARCHAR(255) NULL,
  `type` VARCHAR(64) NULL,
  `amount` DECIMAL(10,2) NULL,
  `sort_order` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sponsor_event_type` (`event_id`, `type`),
  CONSTRAINT `fk_event_id_sponsors` FOREIGN KEY (`event_id`) REFERENCES `wp_fevents_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_logo_id_sponsors` FOREIGN KEY (`logo_id`) REFERENCES `wp_posts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `wp_fevents_people` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(64) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `designation` VARCHAR(255) NULL,
  `is_primary` TINYINT(1) NOT NULL,
  `photo_id` BIGINT UNSIGNED NULL,
  `links` JSON NULL,
  `sort_order` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_people_event_type` (`event_id`, `type`),
  CONSTRAINT `fk_event_id_people` FOREIGN KEY (`event_id`) REFERENCES `wp_fevents_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_photo_id_people` FOREIGN KEY (`photo_id`) REFERENCES `wp_posts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `wp_fevents_notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `recipient_id` BIGINT UNSIGNED NOT NULL,
  `actor_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(64) NOT NULL,
  `object_type` VARCHAR(64) NULL,
  `object_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(255) NULL,
  `content` TEXT NULL,
  `route` TEXT NULL,
  `read_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notification` (`recipient_id`, `action`, `object_id`),
  KEY `idx_notif_recipient` (`recipient_id`, `read_at`),
  KEY `idx_notif_object` (`object_type`, `object_id`),
  KEY `idx_notif_action` (`action`),
  CONSTRAINT `fk_recipient_id_notifications` FOREIGN KEY (`recipient_id`) REFERENCES `wp_users` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_actor_id_notifications` FOREIGN KEY (`actor_id`) REFERENCES `wp_users` (`ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
