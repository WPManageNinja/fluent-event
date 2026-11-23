<?php

namespace FluentEvent\Database\Migrations;

class TicketTypesMigrator
{
    static $tableName = 'fevent_ticket_types';

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
        `event_id` BIGINT(20) UNSIGNED NOT NULL COMMENT 'fevent_event_details.id',
        `tier` VARCHAR(128) NOT NULL COMMENT 'display name, e.g. VIP, General',
        `description` VARCHAR(255) NULL COMMENT 'additional information related to the tier',
        `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `slot` INT NULL COMMENT 'NULL = unlimited',
        `sold` INT NOT NULL DEFAULT 0,
        `min_per_reg` INT NOT NULL DEFAULT 1,
        `max_per_reg` INT NULL COMMENT 'NULL = unlimited',
        `sale_start_at` DATETIME NULL,
        `sale_end_at` DATETIME NULL,
        `status` VARCHAR(64) NOT NULL DEFAULT 'active' COMMENT 'active | inactive',
        `sort_order` INT NOT NULL DEFAULT 0,
        `meta` LONGTEXT NULL,
        `created_at` TIMESTAMP NULL,
        `updated_at` TIMESTAMP NULL,
         INDEX `idx_ticket_types_event` (`event_id`)
    ) $charsetCollate;";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        dbDelta($sql);
    }
}
