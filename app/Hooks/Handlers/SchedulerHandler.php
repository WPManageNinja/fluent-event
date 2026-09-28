<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\App\App;
use FluentEvent\App\Services\EventReminderService;

class SchedulerHandler
{
    public function register()
    {
        add_action('load-toplevel_page_' . App::config()->get('app.slug'), [$this, 'ensureScheduled']);
        add_action('fluent_event/five_minute_tasks', [$this, 'runFiveMinuteTasks']);
    }

    public function ensureScheduled()
    {
        if (!function_exists('as_schedule_recurring_action')) {
            return;
        }

        if (!as_next_scheduled_action('fluent_event/five_minute_tasks')) {
            as_schedule_recurring_action(
                time(),
                5 * MINUTE_IN_SECONDS,
                'fluent_event/five_minute_tasks',
                [],
                'fluent-event',
                true
            );
        }
    }

    public function runFiveMinuteTasks()
    {
        EventReminderService::scheduleDueReminders();
    }
}
