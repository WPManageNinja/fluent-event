<?php

namespace FluentEvent\Database\Migrations;

class EventDetailsMigrator
{
    static $tableName = 'fe_event_details';

    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . static::$tableName;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            return;
        }

        $sql = "CREATE TABLE $table (
        `id` BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
        `post_id` BIGINT(20) UNSIGNED NOT NULL COMMENT 'wp_posts.ID of the fe_event post',
        `calendar_id` BIGINT(20) UNSIGNED NOT NULL COMMENT 'fe_calendars.id',
        `parent_event_id` BIGINT(20) UNSIGNED NULL COMMENT 'recurrence parent',
        `status` VARCHAR(64) NOT NULL DEFAULT 'public' COMMENT 'public | draft',
        `start_at` DATETIME NOT NULL,
        `end_at` DATETIME NOT NULL,
        `timezone` VARCHAR(64) NULL COMMENT 'organizer local time, for display',
        `venue_type` VARCHAR(64) NULL DEFAULT 'offline' COMMENT 'offline | online | hybrid',
        `meta` LONGTEXT NULL COMMENT 'cover_url, online_meeting_url, address, form_fields, entry_type',
        `created_at` TIMESTAMP NULL,
        `updated_at` TIMESTAMP NULL,
         UNIQUE KEY `uq_events_post` (`post_id`),
         INDEX `idx_event_window` (`start_at`, `end_at`),
         INDEX `idx_event_parent` (`parent_event_id`, `start_at`),
         INDEX `idx_events_calendar_startat` (`calendar_id`, `start_at`)
    ) $charsetCollate;";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        dbDelta($sql);
    }
}
