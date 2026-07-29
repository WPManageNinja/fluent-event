<?php

namespace FluentEvent\Database\Migrations;

class CalendarsMigrator
{
    static $tableName = 'fe_calendars';

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
        `title` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(255) NOT NULL,
        `description` TEXT NULL,
        `visibility` VARCHAR(64) NOT NULL DEFAULT 'public' COMMENT 'public | private | unlisted',
        `status` VARCHAR(64) NOT NULL DEFAULT 'active' COMMENT 'active | archived | draft',
        `meta` LONGTEXT NULL COMMENT 'logo_url, cover_url, managers, preferences',
        `created_by` BIGINT(20) UNSIGNED NOT NULL COMMENT 'wp_users.ID of the creator',
        `created_at` TIMESTAMP NULL,
        `updated_at` TIMESTAMP NULL,
         UNIQUE KEY `uq_calendars_slug` (`slug`)
    ) $charsetCollate;";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        dbDelta($sql);
    }
}
