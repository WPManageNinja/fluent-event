<?php

namespace FluentEvent\Database\Migrations;

/**
 * Moves a site from the old `fe_` names to the `fevent` prefix: the tables, the two options,
 * the event posts' post type, saved block templates and menu items.
 *
 * The old names appear here only so the data can leave them; nothing else reads them.
 *
 * Safe to call on every upgrade and from more than one request at once: each step checks
 * before it changes anything, a table that holds rows is never dropped, and the done flag
 * (an autoloaded option) makes later calls free. The flag is set only once no old table is
 * left, so a run that stops halfway is finished by the next request.
 */
class LegacyPrefixMigrator
{
    const DONE_OPTION = 'fevent_prefix_migrated';

    const TABLES = ['event_details', 'ticket_types', 'guests', 'calendars', 'notifications', 'notification_users', 'meta'];

    const OPTIONS = ['fe_settings' => 'fevent_settings', 'fe_version' => 'fevent_version'];

    const OLD_POST_TYPE = 'fe_event';

    const NEW_POST_TYPE = 'fevent_event';

    public static function run()
    {
        if (get_option(self::DONE_OPTION)) {
            return;
        }

        global $wpdb;

        self::renameTables();

        foreach (self::OPTIONS as $old => $new) {
            $value = get_option($old);

            if ($value !== false && get_option($new) === false) {
                update_option($new, $value, true);
            }

            delete_option($old);
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time rename of stored names
        $wpdb->update($wpdb->posts, ['post_type' => self::NEW_POST_TYPE], ['post_type' => self::OLD_POST_TYPE]);

        // Site-editor copies of our block templates are saved under the post type's name.
        foreach (['single', 'archive'] as $kind) {
            $wpdb->update(
                $wpdb->posts,
                ['post_name' => $kind . '-' . self::NEW_POST_TYPE],
                ['post_name' => $kind . '-' . self::OLD_POST_TYPE, 'post_type' => 'wp_template']
            );
        }

        $wpdb->update(
            $wpdb->postmeta,
            ['meta_value' => self::NEW_POST_TYPE],
            ['meta_key' => '_menu_item_object', 'meta_value' => self::OLD_POST_TYPE]
        );
        // phpcs:enable

        // The stored rules route event URLs to the old post type; WordPress rebuilds them on the next load.
        delete_option('rewrite_rules');

        wp_cache_flush();

        if (!self::remainingOldTables()) {
            update_option(self::DONE_OPTION, 1, true);
        }
    }

    protected static function renameTables()
    {
        global $wpdb;

        $suppressed = $wpdb->suppress_errors(true);

        foreach (self::TABLES as $table) {
            $old = $wpdb->prefix . 'fe_' . $table;
            $new = $wpdb->prefix . 'fevent_' . $table;

            // Already moved, maybe by a request running at the same time.
            if (!self::tableExists($old)) {
                continue;
            }

            if (self::tableExists($new)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are fixed
                if ((int) $wpdb->get_var("SELECT COUNT(*) FROM `$new`")) {
                    continue; // Never overwrite data; the old table stays and the flag stays unset.
                }

                // An empty copy that activation created before this ran.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are fixed
                $wpdb->query("DROP TABLE `$new`");
            }

            // The same statement works on MySQL, MariaDB and SQLite.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are fixed
            $wpdb->query("ALTER TABLE `$old` RENAME TO `$new`");
        }

        $wpdb->suppress_errors($suppressed);
    }

    protected static function remainingOldTables()
    {
        global $wpdb;

        return array_filter(self::TABLES, function ($table) use ($wpdb) {
            return self::tableExists($wpdb->prefix . 'fe_' . $table);
        });
    }

    protected static function tableExists($table)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }
}
