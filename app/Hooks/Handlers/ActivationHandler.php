<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\Database\DBMigrator;
use FluentEvent\Framework\Foundation\Application;

class ActivationHandler
{
    /**
     * Application container injected by the framework.
     *
     * @var \FluentEvent\Framework\Foundation\Application|null
     */
    protected $app = null;

    /**
     * Store the Application instance so activation routines can reach the
     * container (config, db, etc.) without globals.
     *
     * @param  \FluentEvent\Framework\Foundation\Application  $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Activation entry point. Delegates to single-site or multisite flow
     * based on the current WordPress install type.
     *
     * @param  bool  $networkWide  True when WP passes the network-wide flag
     *                             to register_activation_hook().
     * @return void
     */
    public function handle($networkWide = false)
    {
        if (!$this->app->isMultisite()) {
            $this->handleSinglesite();
        } else {
            $this->handleMultisite($networkWide);
        }
    }

    /**
     * Activation on a single-site install — run once against the only site.
     *
     * @return void
     */
    protected function handleSinglesite()
    {
        $this->activatePlugin();
    }

    /**
     * Activation on a multisite install.
     *
     * When the user ticks "Network Activate", run the activation routine on
     * every blog in the network (super-admin gate). Otherwise run it only
     * against the current blog, gated by the `activate_plugins` capability.
     *
     * @param  bool  $networkWide
     * @return void
     */
    protected function handleMultisite($networkWide)
    {
        if ($networkWide) {
            if (is_super_admin()) {
                foreach (get_sites() as $blog) {
                    switch_to_blog((int) $blog->blog_id);
                    $this->activatePlugin();
                    restore_current_blog();
                }
            }
        } else {
            if (current_user_can('activate_plugins')) {
                $this->activatePlugin();
            }
        }
    }

    /**
     * The actual per-site activation work. Uncomment DBMigrator::migrateUp()
     * to run schema migrations; add capability grants, option seeds, cron
     * scheduling, etc. here.
     *
     * @return void
     */
    protected function activatePlugin()
    {
        // Plugin Activation Code...
        // DBMigrator::migrateUp();
    }
}
