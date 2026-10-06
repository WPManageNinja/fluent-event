<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Meta;
use FluentEvent\App\Utils\DateTimeHelper;

class EventReminderService
{
    const META_KEY = 'reminder_email';

    const BATCH_SIZE = 50;
    const MAX_SENDS_PER_SECOND = 10;
    const MAX_RUN_TIME = 45;

    public static function scheduleDueReminders()
    {
        if (!function_exists('as_schedule_single_action')) return;

        if (!\ActionScheduler::lock()->set('fluent_event_reminder_scheduler')) return;

        $now = time();

        $events = Event::query()
            ->select(['id', 'start_at'])
            ->where('status', 'public')
            ->where('start_at', '>', gmdate(DateTimeHelper::FORMAT, $now))
            ->where('start_at', '<=', gmdate(DateTimeHelper::FORMAT, $now + Settings::MAX_REMINDER_TIME))
            ->get();

        if ($events->isEmpty()) return;

        $eventIds = $events->pluck('id')->all();
        $globalReminder = Settings::get(Settings::EMAIL_NOTIFICATIONS)['reminder_to_guest'];
        $eventReminders = [];

        $overrides = Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->whereIn('object_id', $eventIds)
            ->byKey(Settings::EMAIL_NOTIFICATIONS)
            ->get();

        foreach ($overrides as $override) {
            if (isset($override->value['reminder_to_guest'])) {
                $eventReminders[(int) $override->object_id] = $override->value['reminder_to_guest'];
            }
        }

        $reminderMetas = [];

        $rows = Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('meta_key', 'like', self::META_KEY . ':%')
            ->whereIn('object_id', $eventIds)
            ->get();

        foreach ($rows as $row) {
            $reminderMetas[$row->object_id][$row->meta_key] = $row;
        }

        foreach ($events as $event) {
            $eventId = (int) $event->id;
            $secondsLeft = strtotime($event->start_at . ' UTC') - $now;
            $isClosestDue = true;

            foreach (self::leadTimes($eventReminders[$eventId] ?? $globalReminder) as $leadTime) {
                if ($leadTime < $secondsLeft) continue;

                $metaKey = self::META_KEY . ':' . $leadTime;
                $reminderMeta = $reminderMetas[$eventId][$metaKey] ?? null;

                if (!$reminderMeta) {
                    // Farther reminders due at the same time have already passed; only the closest one is sent.
                    Meta::query()->create([
                        'object_type' => Meta::OBJECT_TYPE_EVENT,
                        'object_id'   => $eventId,
                        'meta_key'    => $metaKey,
                        'value'       => ['status' => $isClosestDue ? 'scheduled' : 'skipped', 'cursor' => 0],
                    ]);

                    if ($isClosestDue) {
                        as_schedule_single_action(time(), 'fluent_event/send_event_reminders_to_guests', [$eventId, $leadTime], 'fluent-event');
                    }
                } elseif (
                    ($reminderMeta->value['status'] ?? '') === 'scheduled'
                    && !as_next_scheduled_action('fluent_event/send_event_reminders_to_guests', [$eventId, $leadTime], 'fluent-event')
                ) {
                    as_schedule_single_action(time(), 'fluent_event/send_event_reminders_to_guests', [$eventId, $leadTime], 'fluent-event');
                }

                $isClosestDue = false;
            }
        }
    }

    public static function sendReminders($eventId, $leadTime = 0)
    {
        $reminderMeta = Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('object_id', $eventId)
            ->byKey(self::META_KEY . ':' . $leadTime)
            ->first();

        if (!$reminderMeta || ($reminderMeta->value['status'] ?? '') !== 'scheduled') return;

        $event = Event::query()->find($eventId);

        if (
            !$event
            || $event->status !== 'public'
            || $event->start_at <= gmdate(DateTimeHelper::FORMAT)
            || !in_array((int) $leadTime, self::leadTimes(self::reminderFor($eventId)), true)
        ) {
            self::finishReminderMeta($reminderMeta, 'skipped');
            return;
        }

        $subject = sprintf(
            /* translators: %s is the event title */
            __('Reminder: %s is coming up', 'fluent-event'),
            $event->title
        );

        /* translators: 1: event title, 2: formatted start date/time, 3: timezone */
        $intro = __('This is a friendly reminder that %1$s starts on %2$s (%3$s).', 'fluent-event');

        $sent = 0;
        $tick = microtime(true);

        do {
            $guests = Guest::query()
                ->where('event_id', $event->id)
                ->whereNotIn('status', ['cancelled', 'not_going'])
                ->where('id', '>', (int) ($reminderMeta->value['cursor'] ?? 0))
                ->orderBy('id', 'ASC')
                ->limit(self::BATCH_SIZE)
                ->get();

            foreach ($guests as $guest) {
                $reminderMeta->value = array_merge($reminderMeta->value, ['cursor' => $guest->id]);
                $reminderMeta->save();

                if (is_email($guest->email)) {
                    Mailer::send($guest->email, $subject, self::buildBody($event, $guest, $intro));
                    $sent++;
                }

                if ((microtime(true) - FLUENT_EVENT_START_TIME) > self::MAX_RUN_TIME) {
                    as_schedule_single_action(time(), 'fluent_event/send_event_reminders_to_guests', [(int) $event->id, (int) $leadTime], 'fluent-event');
                    return;
                }

                if ($sent && $sent % self::MAX_SENDS_PER_SECOND === 0) {
                    $elapsed = microtime(true) - $tick;

                    if ($elapsed < 1) {
                        usleep((int) ((1 - $elapsed) * 1000000));
                    }

                    $tick = microtime(true);
                }
            }
        } while (count($guests) === self::BATCH_SIZE);

        self::finishReminderMeta($reminderMeta, 'completed');

        do_action('fluent_event/guest_reminders_sent', $event, $sent);
    }

    public static function sendConfirmation($guestId)
    {
        if (empty(Settings::get(Settings::EMAIL_NOTIFICATIONS)['registration_confirmation']['enabled'])) return;

        $guest = Guest::query()->find((int) $guestId);

        if (!$guest || $guest->status !== 'going' || !is_email($guest->email)) return;

        $event = Event::query()->find($guest->event_id);

        if (!$event || $event->status === 'draft') return;

        $subject = sprintf(
            /* translators: %s is the event title */
            __('You are registered for %s', 'fluent-event'),
            $event->title
        );

        /* translators: 1: event title, 2: formatted start date/time, 3: timezone */
        $intro = __('You are registered for %1$s on %2$s (%3$s).', 'fluent-event');

        Mailer::send($guest->email, $subject, self::buildBody($event, $guest, $intro));

        do_action('fluent_event/guest/confirmation_sent', $guest, $event);
    }

    // The queued job passes an id; deleting a guest passes the row itself, since it is already gone.
    public static function sendCancellation($guest)
    {
        if (empty(Settings::get(Settings::EMAIL_NOTIFICATIONS)['registration_cancellation']['enabled'])) return;

        if (!is_object($guest)) {
            $guest = Guest::query()->find((int) $guest);

            if (!$guest || !in_array($guest->status, ['not_going', 'cancelled'], true)) return;
        }

        if (!is_email($guest->email)) return;

        $event = Event::query()->find($guest->event_id);

        if (!$event || $event->status === 'draft') return;

        $subject = sprintf(
            /* translators: %s is the event title */
            __('Your registration for %s is cancelled', 'fluent-event'),
            $event->title
        );

        /* translators: 1: event title, 2: formatted start date/time, 3: timezone */
        $intro = __('Your registration for %1$s on %2$s (%3$s) has been cancelled.', 'fluent-event');

        Mailer::send($guest->email, $subject, self::buildGreeting($event, $guest, $intro));

        do_action('fluent_event/guest/cancellation_sent', $guest, $event);
    }

    // Sorted closest first; empty when the reminder is turned off.
    protected static function leadTimes($reminder)
    {
        if (empty($reminder['enabled'])) return [];

        $leadTimes = array_unique(array_filter(array_map(
            [Settings::class, 'reminderInSeconds'],
            (array) ($reminder['times'] ?? [])
        )));

        sort($leadTimes);

        return $leadTimes;
    }

    protected static function reminderFor($eventId)
    {
        $override = Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('object_id', $eventId)
            ->byKey(Settings::EMAIL_NOTIFICATIONS)
            ->first();

        return $override->value['reminder_to_guest']
            ?? Settings::get(Settings::EMAIL_NOTIFICATIONS)['reminder_to_guest'];
    }

    protected static function finishReminderMeta($reminderMeta, $status)
    {
        $reminderMeta->value = array_merge($reminderMeta->value, ['status' => $status]);
        $reminderMeta->save();
    }

    protected static function buildBody($event, $guest, $intro)
    {
        $body = self::buildGreeting($event, $guest, $intro);
        $meta = $event->meta ?: [];

        if ($event->venue_type === 'online' && !empty($meta['online_meeting_url'])) {
            $body .= '<p>' . sprintf(
                /* translators: %s is the meeting link */
                esc_html__('Join online: %s', 'fluent-event'),
                '<a href="' . esc_url($meta['online_meeting_url']) . '">'
                    . esc_html($meta['online_meeting_url']) . '</a>'
            ) . '</p>';
        } elseif (!empty($meta['venue']) || !empty($meta['address'])) {
            $body .= '<p>' . esc_html(trim(
                ($meta['venue'] ?? '') . ' ' . ($meta['address'] ?? '')
            )) . '</p>';
        }

        $body .= '<p>' . esc_html__('See you there!', 'fluent-event') . '</p>';

        return $body;
    }

    // Just the greeting and intro line; cancellations stop here so the meeting link never reaches them.
    protected static function buildGreeting($event, $guest, $intro)
    {
        $startsAt = date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime($event->start_at_local)
        );

        $body = '<p>' . sprintf(
            /* translators: %s is the guest's name */
            esc_html__('Hi %s,', 'fluent-event'),
            esc_html($guest->name ?: __('there', 'fluent-event'))
        ) . '</p>';

        $body .= '<p>' . sprintf(
            esc_html($intro),
            '<strong>' . esc_html($event->title) . '</strong>',
            esc_html($startsAt),
            esc_html($event->timezone)
        ) . '</p>';

        return $body;
    }
}
