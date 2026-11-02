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

        // A site added after network activation needs its own setup. Priority 11: core's
        // wp_initialize_site (10) creates the new site's tables first.
        add_action('wp_initialize_site', function($newSite) use ($app, $file) {
            try {
                ($app->make(ActivationHandler::class))->handleNewSite($newSite, plugin_basename($file));
            } catch (Throwable $e) {
                do_action('fluent_exception', $e);
            }
        }, 11);

        add_action('plugins_loaded', function() use ($app) {
            do_action('fluent_event/loaded', $app);
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
