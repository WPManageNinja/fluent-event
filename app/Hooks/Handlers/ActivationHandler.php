<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\App\CPT\EventPost;
use FluentEvent\Database\DBMigrator;
use FluentEvent\Database\Migrations\LegacyPrefixMigrator;
use FluentEvent\Framework\Foundation\Application;

class ActivationHandler
{
    const DB_VERSION_OPTION = 'fluent_event_db_version';
    const MIGRATION_LOCK = 'fluent_event_db_migration_lock';

    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle($network_wide = false)
    {
        if (!$network_wide) {
            $this->activateSite();
            return;
        }

        // Action Scheduler keeps one queue per site, so each site needs its own requeue.
        foreach (get_sites(['number' => 0, 'fields' => 'ids']) as $blogId) {
            switch_to_blog($blogId);

            try {
                $this->activateSite();
            } finally {
                restore_current_blog();
            }
        }
    }

    public function handleNewSite($site, $pluginBasename)
    {
        // Read the option directly: front-end signup creates sites without wp-admin/includes/plugin.php,
        // so is_plugin_active_for_network() may not exist.
        $networkPlugins = (array) get_site_option('active_sitewide_plugins', []);

        if (!isset($networkPlugins[$pluginBasename])) {
            return;
        }

        switch_to_blog((int) $site->blog_id);

        try {
            $this->activateSite();
        } finally {
            restore_current_blog();
        }
    }

    // A plugin update never fires the activation hook, so new tables and URL rules land here.
    public function maybeUpgrade()
    {
        // The old `fe_` names move on `init` at priority 0, before the post type registers
        // (app/Hooks/actions.php). Until they have, this request registered the default event slug,
        // so creating tables would split the data and flushing would save the wrong URL rules.
        if (!self::legacyNamesMoved()) {
            return;
        }

        $this->maybeMigrate();

        if (get_option('fevent_version') === FLUENT_EVENT_PLUGIN_VERSION) {
            return;
        }

        flush_rewrite_rules(false);

        update_option('fevent_version', FLUENT_EVENT_PLUGIN_VERSION, true);
    }

    // Runs once per FLUENT_EVENT_DB_VERSION: bump it when a migration changes, and every site migrates again.
    protected function maybeMigrate()
    {
        $dbVersion = get_option(self::DB_VERSION_OPTION);

        if ($dbVersion && version_compare($dbVersion, FLUENT_EVENT_DB_VERSION, '>=')) {
            return;
        }

        // Every request during an update reaches this; only one runs the migration.
        if (get_transient(self::MIGRATION_LOCK)) {
            return;
        }

        set_transient(self::MIGRATION_LOCK, 1, 5 * MINUTE_IN_SECONDS);

        // A failed run keeps the lock until it expires, so the next try waits five minutes
        // instead of repeating the failing migration on every request.
        if (DBMigrator::run()) {
            update_option(self::DB_VERSION_OPTION, FLUENT_EVENT_DB_VERSION, true);
            delete_transient(self::MIGRATION_LOCK);
        }
    }

    protected function activateSite()
    {
        // Before DBMigrator, which would otherwise create empty tables under the new names first.
        // While old tables remain, no tables are created; maybeUpgrade() tries again later.
        // Activation registers the post type again below, after the move, so it may run the move itself.
        $migrated = (!class_exists(LegacyPrefixMigrator::class) || LegacyPrefixMigrator::run()) && DBMigrator::run();

        // `init` ran before the plugin was active, so the post type's rules aren't registered yet.
        (new EventPost())->registerPostType();
        flush_rewrite_rules();
        update_option('fevent_version', FLUENT_EVENT_PLUGIN_VERSION, true);

        // Left unset when a table is missing, so maybeUpgrade() tries again.
        if ($migrated) {
            update_option(self::DB_VERSION_OPTION, FLUENT_EVENT_DB_VERSION, true);
        }

        // Deactivation unschedules every queued action, reminder sends included.
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('fluent_event/reschedule_event_reminders', [0], 'fluent-event');
        }
    }

    // Never runs the move: here the post type is already registered, and finishing the move now would
    // flush rules for the default slug. The WordPress.org build leaves the migrator out
    // (`./build.sh --wporg`): a site installed from there never held the old names.
    protected static function legacyNamesMoved()
    {
        return !class_exists(LegacyPrefixMigrator::class) || LegacyPrefixMigrator::isDone();
    }
}
