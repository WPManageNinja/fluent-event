<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\Framework\Foundation\Application;

class DeactivationHandler
{
    /**
     * Application container injected by the framework.
     *
     * @var \FluentEvent\Framework\Foundation\Application|null
     */
    protected $app = null;

    /**
     * Store the Application instance so deactivation routines can reach the
     * container (scheduler, options, etc.) without globals.
     *
     * @param  \FluentEvent\Framework\Foundation\Application  $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Deactivation entry point. Delegates to single-site or multisite flow
     * based on the current WordPress install type.
     *
     * @param  bool  $networkDeactivating  True when WP passes the network
     *                                     deactivation flag to
     *                                     register_deactivation_hook().
     * @return void
     */
    public function handle($networkDeactivating = false)
    {
        if (!$this->app->isMultisite()) {
            $this->handleSinglesite();
        } else {
            $this->handleMultisite($networkDeactivating);
        }
    }

    /**
     * Deactivation on a single-site install — run once against the only site.
     *
     * @return void
     */
    protected function handleSinglesite()
    {
        $this->deactivatePlugin();
    }

    /**
     * Deactivation on a multisite install.
     *
     * When the plugin was network-activated, run the deactivation routine on
     * every blog in the network (super-admin gate). Otherwise run it only
     * against the current blog, gated by the `activate_plugins` capability.
     *
     * @param  bool  $networkDeactivating
     * @return void
     */
    protected function handleMultisite($networkDeactivating)
    {
        if ($networkDeactivating) {
            if (is_super_admin()) {
                foreach (get_sites(['number' => 0, 'fields' => 'ids']) as $blogId) {
                    switch_to_blog($blogId);

                    try {
                        $this->deactivatePlugin();
                    } finally {
                        restore_current_blog();
                    }
                }
            }
        } else {
            if (current_user_can('activate_plugins')) {
                $this->deactivatePlugin();
            }
        }
    }

    /**
     * The actual per-site deactivation work. Add cron un-scheduling, transient
     * cleanup, option removal, etc. here.
     *
     * @return void
     */
    protected function deactivatePlugin()
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('', [], 'fluent-event');
        }

        // The event permalinks live in the saved rewrite rules, and nothing rebuilds them once we stop
        // loading: /<event_slug>/* would keep serving the home page and hide a page with that slug.
        delete_option('rewrite_rules');
    }
}
