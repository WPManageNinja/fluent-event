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

    public static function run()
    {
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
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
