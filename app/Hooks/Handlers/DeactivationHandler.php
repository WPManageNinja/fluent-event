<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\Framework\Foundation\Application;

class DeactivationHandler
{
    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle()
    {
        if (function_exists('\as_unschedule_all_actions')) {
            \as_unschedule_all_actions('fluent_event/five_minute_tasks');
        }
    }
}
