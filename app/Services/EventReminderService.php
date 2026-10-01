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

        $globalReminder = Settings::get(Settings::EMAIL_NOTIFICATIONS)['reminder_to_guest'];
        $eventReminders = [];

        $overrides = Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->byKey(Settings::EMAIL_NOTIFICATIONS)
            ->get();

        foreach ($overrides as $override) {
            if (isset($override->value['reminder_to_guest'])) {
                $eventReminders[(int) $override->object_id] = $override->value['reminder_to_guest'];
            }
        }

        $maxLeadTime = 0;

        foreach (array_merge([$globalReminder], $eventReminders) as $reminder) {
            $maxLeadTime = max(array_merge([$maxLeadTime], self::leadTimes($reminder)));
        }

        if (!$maxLeadTime) return;

        $now = time();

        $events = Event::query()
            ->select(['id', 'start_at'])
            ->where('status', 'public')
            ->where('start_at', '>', gmdate(DateTimeHelper::FORMAT, $now))
            ->where('start_at', '<=', gmdate(DateTimeHelper::FORMAT, $now + $maxLeadTime))
            ->get();

        if ($events->isEmpty()) return;

        $reminderMetas = [];

        $rows = Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('meta_key', 'like', self::META_KEY . ':%')
            ->whereIn('object_id', $events->pluck('id')->all())
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
                    Mailer::send($guest->email, $subject, self::buildBody($event, $guest));
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

    protected static function buildBody($event, $guest)
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
            /* translators: 1: event title, 2: formatted start date/time, 3: timezone */
            esc_html__('This is a friendly reminder that %1$s starts on %2$s (%3$s).', 'fluent-event'),
            '<strong>' . esc_html($event->title) . '</strong>',
            esc_html($startsAt),
            esc_html($event->timezone)
        ) . '</p>';

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
}
