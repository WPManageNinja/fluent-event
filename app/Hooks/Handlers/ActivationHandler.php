<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\App\Services\EventPageService;
use FluentEvent\App\Services\Settings;
use FluentEvent\Database\DBMigrator;
use FluentEvent\Framework\Foundation\Application;

class ActivationHandler
{
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

    // A plugin update never fires the activation hook, so new tables and URL rules land here once per version.
    public function maybeUpgrade()
    {
        if (get_option('fe_version') === FLUENT_EVENT_PLUGIN_VERSION) {
            return;
        }

        DBMigrator::run();
        flush_rewrite_rules(false);

        update_option('fe_version', FLUENT_EVENT_PLUGIN_VERSION, true);
    }

    protected function activateSite()
    {
        DBMigrator::run();

        // Our public pages, once: a page the admin still has (repointed or not) is left alone, and a deleted one comes back.
        foreach (array_keys(EventPageService::PAGES) as $key) {
            $page = get_post((int) Settings::get()[$key]);

            if (!$page || $page->post_type !== 'page' || $page->post_status === 'trash') {
                EventPageService::createPage($key);
            }
        }

        (new FrontEndHandler())->addRewriteRule();
        flush_rewrite_rules();
        update_option('fe_version', FLUENT_EVENT_PLUGIN_VERSION, true);

        // Deactivation unschedules every queued action, reminder sends included.
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('fluent_event/reschedule_event_reminders', [0], 'fluent-event');
        }
    }
}
