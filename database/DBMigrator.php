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

    /**
     * @return bool False when a table is still missing, so the caller holds the stored DB version back
     *              and the next request retries. dbDelta() does not report a CREATE TABLE the server refused.
     */
    public static function run()
    {
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        self::migrate();

        return !self::missingTables();
    }

    /**
     * @return string[] The plugin's tables, with prefix, that do not exist on the current site.
     */
    public static function missingTables()
    {
        global $wpdb;

        $missing = [];

        foreach (static::getMigrations() as $migration) {
            $table = $wpdb->prefix . $migration::$tableName;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
                $missing[] = $table;
            }
        }

        return $missing;
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
