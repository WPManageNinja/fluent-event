<?php

namespace FluentEvent\Database\Migrations;

use FluentEvent\App\Hooks\Handlers\ActivationHandler;

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
 * `?fe_calendar=`, `?fe_search=` or `?post_type=fe_event` link is redirected to the same URL
 * with the new names.
 *
 * A site that never ran the old FluentEvent (no db version, no events table) is marked done untouched.
 */
class LegacyPrefixMigrator
{
    const DONE_OPTION = 'fevent_prefix_migrated';

    const RETRY_TRANSIENT = 'fevent_prefix_migration_retry';

    const RETRY_DELAY = 10 * MINUTE_IN_SECONDS;

    const TABLES = ['event_details', 'ticket_types', 'guests', 'calendars', 'notifications', 'notification_users', 'meta'];

    const OLD_SETTINGS_OPTION = 'fe_settings';

    const OPTIONS = [self::OLD_SETTINGS_OPTION => 'fevent_settings'];

    // Version options dropped on the move. With no version stored, ActivationHandler::maybeUpgrade()
    // flushes the URL rules, after the post type registered with the moved settings' event slug.
    const VERSION_OPTIONS = ['fe_version', 'fevent_version'];

    /**
     * What run() returned in this request. Hooked on `init` at priority 0, it runs before the post
     * type registers, so true means the registration read the moved settings (the custom event slug).
     *
     * @var bool|null
     */
    protected static $done = null;

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

        // Plain permalinks put the archive (the events list when no page is chosen) at ?post_type=<type>.
        if (isset($query['post_type']) && $query['post_type'] === self::OLD_POST_TYPE) {
            $query['post_type'] = self::NEW_POST_TYPE;
            $renamed = true;
        }

        if (!$renamed) {
            return null;
        }

        // One leading slash: `//host/...` would otherwise read as another site.
        $path = '/' . ltrim((string) strtok((string) $requestUri, '?'), '/');

        return $query ? $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : $path;
    }

    /**
     * Hooked on `default_option_fevent_settings`: while the new option does not exist, reads get the old
     * one. A paused move (see run()) thus still shows the site's own settings, and a save during the
     * pause writes them into the new option together with the edit, so nothing is lost when the move
     * later deletes the old option.
     *
     * @param mixed $default
     * @return mixed
     */
    public static function legacySettingsFallback($default)
    {
        $legacy = get_option(self::OLD_SETTINGS_OPTION);

        return is_array($legacy) ? $legacy : $default;
    }

    /**
     * @return bool True when no old table is left, so the caller may create or update tables.
     *              False while old tables remain: creating new ones then would split the data.
     */
    public static function run()
    {
        return self::$done = self::migrate();
    }

    /**
     * Whether the move was complete before the post type registered in this request. A request whose
     * own run() could not finish (another request held the lock) registered the default event slug,
     * so it gets false here even if the other request finishes meanwhile.
     *
     * @return bool
     */
    public static function isDone()
    {
        // No run() in this request (a unit test, or code loaded outside `init`): the stored flag.
        return self::$done ?? (bool) get_option(self::DONE_OPTION);
    }

    protected static function migrate()
    {
        if (get_option(self::DONE_OPTION)) {
            return true;
        }

        // Only a site that ran the old FluentEvent moves: on a fresh install, `fe_settings` or a `wp_fe_*`
        // table is another plugin's. The new events table means an older build's move stopped partway.
        if (!self::wasInstalled()) {
            update_option(self::DONE_OPTION, 1, true);

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
            // Check the new option as stored: the fallback would answer for it with the old value.
            remove_filter('default_option_' . $new, [self::class, 'legacySettingsFallback']);

            $value = get_option($old);

            if ($value !== false && get_option($new) === false) {
                update_option($new, $value, true);
            }

            delete_option($old);
        }

        foreach (self::VERSION_OPTIONS as $option) {
            delete_option($option);
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

        // The stored rules still route event URLs to the old post type. They are not deleted here: a
        // concurrent request that registered the default slug would rebuild them wrong. The version
        // options dropped above make maybeUpgrade() rebuild them once the post type is registered.

        wp_cache_flush();
    }

    protected static function wasInstalled()
    {
        global $wpdb;

        return get_option(ActivationHandler::DB_VERSION_OPTION)
            || self::tableExists($wpdb->prefix . 'fe_event_details')
            || self::tableExists($wpdb->prefix . 'fevent_event_details');
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
