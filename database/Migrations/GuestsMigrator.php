<?php

namespace FluentEvent\Database\Migrations;

class GuestsMigrator
{
    static $tableName = 'fevent_guests';

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
        `calendar_id` BIGINT(20) UNSIGNED NOT NULL COMMENT 'fevent_calendars.id, denormalised',
        `ticket_type_id` BIGINT(20) UNSIGNED NULL COMMENT 'NULL for RSVP, required when ticketed',
        `group_id` VARCHAR(64) NOT NULL COMMENT 'rows sharing this value are one order',
        `is_buyer` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'one per group_id, carries order and payment',
        `name` VARCHAR(255) NULL,
        `email` VARCHAR(191) NULL,
        `user_id` BIGINT(20) UNSIGNED NULL COMMENT 'wp_users.ID when logged in',
        `status` VARCHAR(64) NOT NULL COMMENT 'going | not_going | checked_in | cancelled',
        `hash` VARCHAR(64) NULL COMMENT 'per-ticket token, wp_generate_password',
        `checked_in_at` DATETIME NULL,
        `payment_status` VARCHAR(64) NULL COMMENT 'paid | unpaid | refunded',
        `payment_ref` VARCHAR(255) NULL,
        `price` DECIMAL(10,2) NULL,
        `paid_at` DATETIME NULL,
        `source` VARCHAR(64) NULL COMMENT 'manual | wordpress_pages | fluent_community',
        `source_id` BIGINT(20) UNSIGNED NULL COMMENT 'id in the originating system, scoped by source',
        `meta` LONGTEXT NULL COMMENT 'form_response and other per-guest data',
        `created_at` TIMESTAMP NULL,
        `updated_at` TIMESTAMP NULL,
         UNIQUE KEY `uq_guests_hash` (`hash`),
         UNIQUE KEY `uq_guest_event_email` (`event_id`, `email`),
         UNIQUE KEY `uq_guest_event_user` (`event_id`, `user_id`),
         INDEX `idx_guest_event_status` (`event_id`, `status`),
         INDEX `idx_guest_calendar` (`calendar_id`, `status`),
         INDEX `idx_guest_order` (`group_id`),
         INDEX `idx_guest_email` (`email`),
         INDEX `idx_guests_ticket_type` (`ticket_type_id`),
         INDEX `idx_guest_created` (`created_at`)
    ) $charsetCollate;";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        dbDelta($sql);
    }
}
