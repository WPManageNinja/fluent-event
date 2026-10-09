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
        DBMigrator::run($network_wide);

        // Deactivation unschedules every queued action, reminder sends included.
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('fluent_event/reschedule_event_reminders', [0], 'fluent-event');
        }
    }
}
