<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\App\Services\EventReminderService;

class EmailNotificationHandler
{
    public function register()
    {
        add_action('fluent_event/send_event_reminders_to_guests', [EventReminderService::class, 'sendReminders']);
    }
}
