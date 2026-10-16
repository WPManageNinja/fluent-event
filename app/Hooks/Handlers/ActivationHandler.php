<?php

namespace FluentEvent\App\Hooks\Handlers;

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

    protected function activateSite()
    {
        DBMigrator::run();

        // Deactivation unschedules every queued action, reminder sends included.
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('fluent_event/reschedule_event_reminders', [0], 'fluent-event');
        }
    }
}
