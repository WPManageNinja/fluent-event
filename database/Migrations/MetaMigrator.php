<?php

namespace FluentEvent\Database\Migrations;

class MetaMigrator
{
    static $tableName = 'fe_meta';

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
        `object_type` VARCHAR(50) NOT NULL COMMENT 'option | user | calendar | event',
        `object_id` BIGINT(20) UNSIGNED NULL,
        `meta_key` VARCHAR(191) NOT NULL,
        `value` LONGTEXT NULL,
        `created_at` TIMESTAMP NULL,
        `updated_at` TIMESTAMP NULL,
         INDEX `idx_meta_lookup` (`object_type`, `object_id`, `meta_key`),
         INDEX `idx_meta_object_key` (`object_id`, `meta_key`)
    ) $charsetCollate;";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        dbDelta($sql);
    }
}
