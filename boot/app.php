<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

use FluentEvent\Framework\Foundation\Application;
use FluentEvent\App\Hooks\Handlers\ActivationHandler;
use FluentEvent\App\Hooks\Handlers\DeactivationHandler;
use FluentEvent\Database\DBMigrator;

return function($file) {
    try {
        $app = new Application($file);

        register_activation_hook($file, function($networkWide = false) use ($app) {
            try {
                ($app->make(ActivationHandler::class))->handle($networkWide);
            } catch (Throwable $e) {
                do_action('fluent_exception', $e);
                throw $e;
            }
        });

        register_deactivation_hook($file, function() use ($app) {
            try {
                ($app->make(DeactivationHandler::class))->handle();
            } catch (Throwable $e) {
                do_action('fluent_exception', $e);
            }
        });

        add_action('plugins_loaded', function() use ($app) {
            do_action('fluentevent_loaded', $app);

            // Per-site version check: covers plugin updates and sites added to
            // a network after it was activated, which have no version yet.
            $dbVersion = get_option(DBMigrator::DB_VERSION_OPTION);

            if (
                (!$dbVersion || version_compare($dbVersion, FLUENT_EVENT_DB_VERSION, '<'))
                && !get_transient('fluent_event_db_migration_lock')
            ) {
                set_transient('fluent_event_db_migration_lock', 1, 5 * MINUTE_IN_SECONDS);

                try {
                    DBMigrator::migrate();
                } catch (Throwable $e) {
                    do_action('fluent_exception', $e);
                } finally {
                    delete_transient('fluent_event_db_migration_lock');
                }
            }
        });

        return $app;

    } catch (Throwable $e) {
        try {
            $message = sprintf(
                "%s in %s:%d\nStack trace:\n%s\n",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            
            if ((defined('WP_DEBUG') && WP_DEBUG) || defined('FLUENT_BRIDGE_SECRET')) {
                error_log($message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }

            do_action('fluent_exception', $e);
        } catch (Throwable $inner) {
            if ((defined('WP_DEBUG') && WP_DEBUG) || defined('FLUENT_BRIDGE_SECRET')) {
                error_log("Exception in exception handler: " . $inner->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
        }
    }
};
