<?php

namespace FluentEvent\Database\Migrations;

class NotificationsMigrator
{
    static $tableName = 'fe_notifications';

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
        `user_id` BIGINT(20) UNSIGNED NULL COMMENT 'actor who caused it',
        `action` VARCHAR(128) NOT NULL,
        `object_type` VARCHAR(64) NULL,
        `object_id` BIGINT(20) UNSIGNED NULL,
        `title` VARCHAR(255) NULL,
        `content` TEXT NULL,
        `route` TEXT NULL,
        `created_at` TIMESTAMP NULL
    ) $charsetCollate;";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        dbDelta($sql);
    }
}
