-- Fluent Events — Database Schema (DrawSQL import)
-- 5 plugin tables (wp_fevents_*) + WP core stubs (wp_users, wp_posts).
-- Attendee identity: WP users (hosts/managers) + email-only guests (wp_fevents_guests).
-- No custom profile table — profile pivot removed (email-only entry, config via wp-admin).
-- Media: WP media library — image columns store a wp_posts attachment ID (no custom media table).
-- Tables ordered referenced-first so inline FKs resolve on import.
-- Polymorphic slots (object_type/object_id) are indexed columns, NOT foreign keys
--   (one column can't reference many tables). Kept as plain BIGINT + composite index.
-- wp_users / wp_posts are minimal stubs — present only so FK relationships render.

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- WP core (stub — FK target only)
-- ============================================================

CREATE TABLE `wp_users` (
  `ID`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_login`   VARCHAR(60)  NOT NULL DEFAULT '',
  `user_email`   VARCHAR(100) NOT NULL DEFAULT '',
  `display_name` VARCHAR(250) NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WP media library: image columns store an attachment ID (post_type='attachment').
CREATE TABLE `wp_posts` (
  `ID` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Attachment / post ID',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- wp_fevents_calendars
-- ============================================================

CREATE TABLE `wp_fevents_calendars` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(255) NOT NULL COMMENT 'Calendar name',
  `slug`           VARCHAR(191) NOT NULL COMMENT 'Unique shareable URL',
  `description`    TEXT         NULL,
  `logo_media_id`  BIGINT UNSIGNED NULL COMMENT 'WP attachment ID (wp_posts)',
  `cover_media_id` BIGINT UNSIGNED NULL COMMENT 'WP attachment ID (wp_posts)',
  `visibility`     VARCHAR(20)  NOT NULL DEFAULT 'public' COMMENT 'public|unlisted|private',
  `settings`       JSON         NULL COMMENT 'theme, manage caps, notif prefs',
  `created_by`     BIGINT UNSIGNED NOT NULL COMMENT 'host/manager user id',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_calendar_slug` (`slug`),
  KEY `idx_calendar_visibility` (`visibility`),
  CONSTRAINT `fk_calendar_logo`    FOREIGN KEY (`logo_media_id`)  REFERENCES `wp_posts` (`ID`) ON DELETE SET NULL,
  CONSTRAINT `fk_calendar_cover`   FOREIGN KEY (`cover_media_id`) REFERENCES `wp_posts` (`ID`) ON DELETE SET NULL,
  CONSTRAINT `fk_calendar_creator` FOREIGN KEY (`created_by`)     REFERENCES `wp_users` (`ID`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- wp_fevents_events
-- ============================================================

CREATE TABLE `wp_fevents_events` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`              VARCHAR(255) NOT NULL COMMENT 'Event name',
  `slug`               VARCHAR(191) NOT NULL COMMENT 'URL-friendly identifier',
  `description`        LONGTEXT     NULL COMMENT 'Event details/content',
  `status`             VARCHAR(20)  NOT NULL DEFAULT 'draft' COMMENT 'draft|published|archived',
  `visibility`         VARCHAR(20)  NOT NULL DEFAULT 'public' COMMENT 'public|unlisted|private',
  `start_at`           DATETIME     NOT NULL COMMENT 'Event start in timezone',
  `end_at`             DATETIME     NOT NULL COMMENT 'Event end in timezone',
  `timezone`           VARCHAR(64)  NOT NULL COMMENT 'IANA timezone (DST-aware)',
  `calendar_id`        BIGINT UNSIGNED NOT NULL COMMENT 'Parent calendar (v1: default)',
  `parent_event_id`    BIGINT UNSIGNED NULL COMMENT 'Master in recurring series',
  `location_address`   VARCHAR(255) NULL COMMENT 'Physical address (Google Places)',
  `location_lat`       DECIMAL(10,8) NULL COMMENT 'Latitude coordinate',
  `location_lng`       DECIMAL(11,8) NULL COMMENT 'Longitude coordinate',
  `online_meeting_url` VARCHAR(255) NULL COMMENT 'Google Meet or Zoom link',
  `capacity`           INT          NULL COMMENT 'Max attendees; null = unlimited',
  `created_by`         BIGINT UNSIGNED NOT NULL COMMENT 'Host/creator user',
  `cover_image_id`     BIGINT UNSIGNED NULL COMMENT 'Featured image — WP attachment ID (wp_posts)',
  `settings`           JSON         NULL COMMENT 'Per-event config; recurrence rule',
  `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_slug` (`slug`),
  KEY `idx_event_calendar` (`calendar_id`),
  KEY `idx_event_creator`  (`created_by`),
  KEY `idx_event_state`    (`status`, `visibility`),
  KEY `idx_event_window`   (`start_at`, `end_at`),
  CONSTRAINT `fk_event_calendar` FOREIGN KEY (`calendar_id`)     REFERENCES `wp_fevents_calendars` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_event_parent`   FOREIGN KEY (`parent_event_id`) REFERENCES `wp_fevents_events` (`id`)    ON DELETE SET NULL,
  CONSTRAINT `fk_event_creator`  FOREIGN KEY (`created_by`)      REFERENCES `wp_users` (`ID`)  ON DELETE RESTRICT,
  CONSTRAINT `fk_event_cover`    FOREIGN KEY (`cover_image_id`)  REFERENCES `wp_posts` (`ID`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- wp_fevents_guests
-- ============================================================

CREATE TABLE `wp_fevents_guests` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`       BIGINT UNSIGNED NOT NULL COMMENT 'Parent event',
  `name`           VARCHAR(191) NULL COMMENT 'Guest-provided name (anonymous entry)',
  `email`          VARCHAR(191) NOT NULL COMMENT 'Guest email — identity key for an anonymous guest',
  `status`         VARCHAR(20)  NOT NULL COMMENT 'invited|requested|approved|attended',
  `source`         VARCHAR(20)  NOT NULL COMMENT 'invite|link|import|request',
  `form_response`  JSON         NULL COMMENT 'Join form answers',
  `settings`       JSON         NULL COMMENT 'Per-guest prefs/overrides',
  `payment_status` VARCHAR(20)  NULL COMMENT 'pending|paid|refunded|failed',
  `payment_ref`    VARCHAR(191) NULL COMMENT 'Paymattic transaction/submission',
  `amount`         DECIMAL(10,2) NULL COMMENT 'Snapshot of amount paid',
  `currency`       VARCHAR(3)   NULL COMMENT 'ISO currency code snapshot',
  `paid_at`        DATETIME     NULL COMMENT 'Payment timestamp',
  `responded_at`   DATETIME     NULL COMMENT 'RSVP timestamp',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_guest_event_status` (`event_id`, `status`),
  KEY `idx_guest_email`        (`email`),
  CONSTRAINT `fk_guest_event`   FOREIGN KEY (`event_id`)   REFERENCES `wp_fevents_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- wp_fevents_notifications  ◇ polymorphic (object_type/object_id)
-- ============================================================

CREATE TABLE `wp_fevents_notifications` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `recipient_id` BIGINT UNSIGNED NOT NULL COMMENT 'User who sees the notification',
  `actor_id`     BIGINT UNSIGNED NULL COMMENT 'User who triggered it; null = system',
  `action`       VARCHAR(64)  NOT NULL COMMENT 'Typed action (e.g. event.join)',
  `object_type`  VARCHAR(32)  NULL COMMENT 'Type of object (event, calendar, ...)',
  `object_id`    BIGINT UNSIGNED NULL COMMENT 'ID of the related object',
  `data`         JSON         NULL COMMENT 'Render context: names, titles',
  `route`        TEXT         NULL COMMENT 'Deep link to relevant portal',
  `read_at`      DATETIME     NULL COMMENT 'When read; null = unread',
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When notification was created',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notification` (`recipient_id`, `action`, `object_id`),
  KEY `idx_notif_recipient` (`recipient_id`, `read_at`),
  KEY `idx_notif_object`    (`object_type`, `object_id`),
  KEY `idx_notif_action`    (`action`),
  CONSTRAINT `fk_notif_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `wp_users` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_actor`     FOREIGN KEY (`actor_id`)     REFERENCES `wp_users` (`ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- wp_fevents_meta  ◇ polymorphic (object_type/object_id) — no FK
-- ============================================================

CREATE TABLE `wp_fevents_meta` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `object_type` VARCHAR(20)  NOT NULL COMMENT 'option|user|event|calendar',
  `object_id`   BIGINT UNSIGNED NULL COMMENT 'user_id/event_id/calendar_id; null for option',
  `meta_key`    VARCHAR(191) NOT NULL COMMENT 'Section name or meta key',
  `value`       LONGTEXT     NULL COMMENT 'JSON or serialized data',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_meta_lookup` (`object_type`, `object_id`, `meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
