<?php defined('ABSPATH') or die;

/**
 * Registers the bundled Action Scheduler (vendor/woocommerce/action-scheduler)
 * using the standard shared-version pattern: every bundling plugin registers
 * its copy and ActionScheduler_Versions boots the newest one at `plugins_loaded`.
 */
if (!function_exists('fluentevent_scheduler_register') && function_exists('add_action')) { // WRCS: DEFINED_VERSION.

    if (!class_exists('ActionScheduler_Versions', false)) {
        require_once FLUENT_EVENT_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/classes/ActionScheduler_Versions.php';
        add_action('plugins_loaded', array('ActionScheduler_Versions', 'initialize_latest_version'), 1, 0);
    }

    add_action('plugins_loaded', 'fluentevent_scheduler_register', 0, 0); // WRCS: DEFINED_VERSION.

    /**
     * Registers this version of Action Scheduler.
     */
    function fluentevent_scheduler_register()
    { // WRCS: DEFINED_VERSION.
        $versions = ActionScheduler_Versions::instance();
        $versions->register('4.2.0', 'fluentevent_scheduler_initialize'); // WRCS: DEFINED_VERSION.
    }

    /**
     * Initializes this version of Action Scheduler.
     */
    function fluentevent_scheduler_initialize()
    { // WRCS: DEFINED_VERSION.
        // A final safety check is required even here, because historic versions of Action Scheduler
        // followed a different pattern (in some unusual cases, we could reach this point and the
        // ActionScheduler class is already defined—so we need to guard against that).
        if (!class_exists('ActionScheduler', false)) {
            require_once FLUENT_EVENT_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/classes/abstracts/ActionScheduler.php';
            ActionScheduler::init(FLUENT_EVENT_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php');
        }
    }

    // Support usage in themes - load this version if no plugin has loaded a version yet.
    if (did_action('plugins_loaded') && !doing_action('plugins_loaded') && !class_exists('ActionScheduler', false)) {
        fluentevent_scheduler_initialize(); // WRCS: DEFINED_VERSION.
        do_action('action_scheduler_pre_theme_init'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        ActionScheduler_Versions::initialize_latest_version();
    }
}
