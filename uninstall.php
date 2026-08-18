<?php
/**
 * Runs when the plugin is deleted from wp-admin. Removes all plugin
 * data only if the "Remove data on uninstall" setting is turned on —
 * kept opt-in so a delete/reinstall never destroys data by accident.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

function fluent_event_uninstall_site()
{
    $settings = get_option('fe_settings', []);

    $remove = is_array($settings)
        && ($settings['remove_data_on_uninstall'] ?? 'no') === 'yes';

    if (!$remove) return;

    global $wpdb;

    // every table the plugin ever created, straight from the migrators
    foreach (FluentEvent\Database\DBMigrator::getMigrations() as $migration) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup, table names come from our own migrators
        $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}{$migration::$tableName}`");
    }

    $postIds = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
        'fe_event'
    ));

    if ($postIds) {
        $ids = implode(',', array_map('absint', $postIds));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup, ids are absint-cast above
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$ids})");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup, ids are absint-cast above
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID IN ({$ids})");
    }

    delete_option('fe_settings');
}

if (is_multisite()) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup
    $fluentEventBlogIds = $GLOBALS['wpdb']->get_col(
        "SELECT blog_id FROM {$GLOBALS['wpdb']->blogs}"
    );

    foreach ($fluentEventBlogIds as $fluentEventBlogId) {
        switch_to_blog($fluentEventBlogId);
        try {
            fluent_event_uninstall_site();
        } finally {
            restore_current_blog();
        }
    }
} else {
    fluent_event_uninstall_site();
}
