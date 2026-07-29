<?php

namespace FluentEvent\Database;

use FluentEvent\Database\Migrations\CalendarsMigrator;
use FluentEvent\Database\Migrations\EventDetailsMigrator;
use FluentEvent\Database\Migrations\GuestsMigrator;
use FluentEvent\Database\Migrations\MetaMigrator;
use FluentEvent\Database\Migrations\NotificationsMigrator;
use FluentEvent\Database\Migrations\NotificationUsersMigrator;
use FluentEvent\Database\Migrations\TicketTypesMigrator;

class DBMigrator
{
    private static $migrations = [
        CalendarsMigrator::class,
        EventDetailsMigrator::class,
        TicketTypesMigrator::class,
        GuestsMigrator::class,
        MetaMigrator::class,
        NotificationsMigrator::class,
        NotificationUsersMigrator::class
    ];

    public static function run($network_wide = false)
    {
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        if ($network_wide) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for multisite network activation
            $blogs = $wpdb->get_results("SELECT blog_id FROM $wpdb->blogs");
            foreach ($blogs as $blog) {
                switch_to_blog($blog->blog_id);
                self::migrate();
                restore_current_blog();
            }
            return;
        }

        self::migrate();
    }

    public static function migrate()
    {
        foreach (static::getMigrations() as $migration) {
            $migration::migrate();
        }
    }

    public static function getMigrations()
    {
        return static::$migrations;
    }
}
