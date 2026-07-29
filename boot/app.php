<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

use FluentEvent\Framework\Foundation\Application;
use FluentEvent\App\Hooks\Handlers\ActivationHandler;
use FluentEvent\App\Hooks\Handlers\DeactivationHandler;

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

        register_deactivation_hook($file, function($networkDeactivating = false) use ($app) {
            try {
                ($app->make(DeactivationHandler::class))->handle($networkDeactivating);
            } catch (Throwable $e) {
                do_action('fluent_exception', $e);
            }
        });

        // Multisite new-site coverage: when a super-admin creates a new blog
        // in the network, run activation against it for plugins activated
        // network-wide. Without this, the new site has the plugin "active"
        // but un-activated (no DB tables, no seeded options).
        add_action('wp_insert_site', function($newSite) use ($app, $file) {
            if (!is_plugin_active_for_network(plugin_basename($file))) {
                return;
            }

            switch_to_blog((int) $newSite->blog_id);

            try {
                ($app->make(ActivationHandler::class))->handle(false);
            } catch (Throwable $e) {
                do_action('fluent_exception', $e);
            } finally {
                // Always restore — even if activation throws, we must not
                // leave the request stuck in the new blog's context.
                restore_current_blog();
            }
        });

        add_action('plugins_loaded', function() use ($app) {
            do_action('fluentevent_loaded', $app);
        });

        // register_activation_hook does not fire on an in-place plugin update,
        // so without this a table added in a later release would never reach an
        // existing install. Every migrator returns early when its table already
        // exists, so this costs a few SHOW TABLES LIKE queries on a page that is
        // already doing far more work.
        add_action('fluent_event/admin_app_rendering', function() {
            \FluentEvent\Database\DBMigrator::run();
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
