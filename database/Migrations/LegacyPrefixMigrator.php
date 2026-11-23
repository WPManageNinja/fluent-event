<?php

namespace FluentEvent\Database\Migrations;

/**
 * Moves a site from the old `fe_` names to the `fevent` prefix: the tables, the two options,
 * the event posts' post type, saved block templates and menu items.
 *
 * The old names appear here only so the data can leave them; nothing else reads them.
 *
 * Tables move first. Only when no old table is left do the options, posts, templates and menu
 * items move, and the done flag (an autoloaded option) is set; after that every call is free.
 * A table that holds rows is never dropped. A run that cannot finish (a rename refused, or a
 * new-name table that already holds rows) leaves the flag unset and retries at most every
 * RETRY_DELAY seconds. A MySQL named lock keeps two requests from moving tables at once.
 *
 * It also keeps links shared before the rename working: an old `?fe_event=`, `?fe_page=`,
 * `?fe_calendar=` or `?fe_search=` link is redirected to the same URL with the new names.
 */
class LegacyPrefixMigrator
{
    const DONE_OPTION = 'fevent_prefix_migrated';

    const RETRY_TRANSIENT = 'fevent_prefix_migration_retry';

    const RETRY_DELAY = 10 * MINUTE_IN_SECONDS;

    const TABLES = ['event_details', 'ticket_types', 'guests', 'calendars', 'notifications', 'notification_users', 'meta'];

    const OPTIONS = ['fe_settings' => 'fevent_settings', 'fe_version' => 'fevent_version'];

    const OLD_POST_TYPE = 'fe_event';

    const NEW_POST_TYPE = 'fevent_event';

    // Query args in links made before the rename. `fe_event` is the post type's own query var,
    // which plain permalinks use for event links; the others are the events list's filters.
    const LEGACY_QUERY_ARGS = [
        'fe_event'    => 'fevent_event',
        'fe_page'     => 'fevent_page',
        'fe_calendar' => 'fevent_calendar',
        'fe_search'   => 'fevent_search',
    ];

    // Hooked on template_redirect, before the event page resolves; the new URL then goes through
    // the usual checks, so an unlisted event still opens and a private one still does not.
    public static function redirectLegacyLink()
    {
        if (empty($_GET)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only link translation
            return;
        }

        $target = self::legacyLinkTarget(
            wp_unslash($_GET), // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only re-encoded into the redirect URL; the page sanitizes each value when it reads it
            wp_unslash($_SERVER['REQUEST_URI'] ?? '/') // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- path only; wp_safe_redirect() sanitizes the URL
        );

        if ($target === null) {
            return;
        }

        wp_safe_redirect($target, 301);
        exit;
    }

    /**
     * @param array  $query      The request's query args, unslashed.
     * @param string $requestUri The request path, with or without a query string.
     * @return string|null The same path with the old arg names renamed, or null when there is none.
     */
    public static function legacyLinkTarget(array $query, $requestUri)
    {
        $renamed = false;

        foreach (self::LEGACY_QUERY_ARGS as $old => $new) {
            if (!array_key_exists($old, $query)) {
                continue;
            }

            $value = $query[$old];
            unset($query[$old]);
            $renamed = true;

            // A link that already carries the new name keeps it; a non-scalar value is dropped.
            if (!array_key_exists($new, $query) && is_scalar($value)) {
                $query[$new] = (string) $value;
            }
        }

        if (!$renamed) {
            return null;
        }

        // One leading slash: `//host/...` would otherwise read as another site.
        $path = '/' . ltrim((string) strtok((string) $requestUri, '?'), '/');

        return $query ? $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $path;
    }

    /**
     * @return bool True when no old table is left, so the caller may create or update tables.
     *              False while old tables remain: creating new ones then would split the data.
     */
    public static function run()
    {
        if (get_option(self::DONE_OPTION)) {
            return true;
        }

        if (get_transient(self::RETRY_TRANSIENT)) {
            return false;
        }

        $lock = self::lock();

        // Another request is moving the tables right now.
        if ($lock === false) {
            return false;
        }

        try {
            self::renameTables();

            if ($remaining = self::remainingOldTables()) {
                set_transient(self::RETRY_TRANSIENT, 1, self::RETRY_DELAY);

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('FluentEvent: could not move these tables to the fevent prefix: ' . implode(', ', $remaining));
                }

                return false;
            }

            self::moveStoredNames();
            update_option(self::DONE_OPTION, 1, true);

            return true;
        } finally {
            self::unlock($lock);
        }
    }

    protected static function renameTables()
    {
        global $wpdb;

        $suppressed = $wpdb->suppress_errors(true);

        foreach (self::TABLES as $table) {
            $old = $wpdb->prefix . 'fe_' . $table;
            $new = $wpdb->prefix . 'fevent_' . $table;

            if (!self::tableExists($old)) {
                continue;
            }

            if (self::tableExists($new)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are fixed
                if ((int) $wpdb->get_var("SELECT COUNT(*) FROM `$new`")) {
                    continue; // Never overwrite data; the old table stays and the flag stays unset.
                }

                // An empty copy an earlier build created. Safe under the lock: no other request is renaming.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are fixed
                $wpdb->query("DROP TABLE `$new`");
            }

            // The same statement works on MySQL, MariaDB and SQLite.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are fixed
            $wpdb->query("ALTER TABLE `$old` RENAME TO `$new`");
        }

        $wpdb->suppress_errors($suppressed);
    }

    // Runs once, after every table has moved.
    protected static function moveStoredNames()
    {
        global $wpdb;

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
    }

    protected static function remainingOldTables()
    {
        global $wpdb;

        return array_values(array_filter(self::TABLES, function ($table) use ($wpdb) {
            return self::tableExists($wpdb->prefix . 'fe_' . $table);
        }));
    }

    protected static function tableExists($table)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    /**
     * @return string|false|null The lock name when held, false when another request holds it,
     *                           null when the database has no named locks (SQLite): run unlocked.
     */
    protected static function lock()
    {
        global $wpdb;

        // GET_LOCK is server-wide, so the database and prefix keep two sites on one server apart.
        $name = 'fevent_prefix_' . md5(constant('DB_NAME') . ':' . $wpdb->prefix);

        $suppressed = $wpdb->suppress_errors(true);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- MySQL advisory lock; a cached result would defeat the lock
        $locked = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, 0));
        $wpdb->suppress_errors($suppressed);

        if ($locked === null) {
            return null;
        }

        return (string) $locked === '1' ? $name : false;
    }

    protected static function unlock($lock)
    {
        if (!is_string($lock)) {
            return;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- MySQL advisory lock
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
}
