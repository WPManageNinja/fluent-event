<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\App\Services\EventReminderService;

class EmailNotificationHandler
{
    public function register()
    {
        add_action('fluent_event/send_event_reminders_to_guests', [EventReminderService::class, 'sendReminders'], 10, 2);
        add_action('fluent_event/send_registration_confirmation', [EventReminderService::class, 'sendConfirmation']);
        add_action('fluent_event/send_registration_cancellation', [EventReminderService::class, 'sendCancellation']);

        add_action('fluent_event/event/created', [EventReminderService::class, 'scheduleReminders']);
        add_action('fluent_event/event/updated', [EventReminderService::class, 'rescheduleReminders']);
        // Before CleanupHandler (10) deletes the reminder meta that names the queued sends.
        add_action('fluent_event/event/before_deleted', [EventReminderService::class, 'cancelReminders'], 5);
        add_action('fluent_event/settings/email_notifications_updated', [EventReminderService::class, 'rescheduleAllReminders']);
        add_action('fluent_event/reschedule_event_reminders', [EventReminderService::class, 'rescheduleUpcoming']);
    }
}
