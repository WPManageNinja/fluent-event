<?php

namespace FluentEvent\Database\Migrations;

class NotificationUsersMigrator
{
    static $tableName = 'fe_notification_users';

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
        `object_type` VARCHAR(64) NOT NULL COMMENT 'notification | notification_pref',
        `notification_type` VARCHAR(128) NOT NULL,
        `object_id` BIGINT(20) UNSIGNED NOT NULL,
        `user_id` BIGINT(20) UNSIGNED NOT NULL,
        `is_read` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP NULL,
        `updated_at` TIMESTAMP NULL,
         UNIQUE KEY `uq_notif_user` (`user_id`, `object_type`, `object_id`, `notification_type`),
         INDEX `idx_notif_user_inbox` (`user_id`, `is_read`, `object_type`),
         INDEX `idx_notif_user_object` (`object_id`, `is_read`, `object_type`, `notification_type`)
    ) $charsetCollate;";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        dbDelta($sql);
    }
}
