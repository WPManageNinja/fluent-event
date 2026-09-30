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

        $hours = (int) Settings::get(Settings::EMAIL)['reminder_hours'] ?: 24;

        $eventIds = Event::query()
            ->where('status', 'public')
            ->where('start_at', '>', gmdate(DateTimeHelper::FORMAT))
            ->where('start_at', '<=', gmdate(DateTimeHelper::FORMAT, time() + $hours * HOUR_IN_SECONDS))
            ->pluck('id')
            ->all();

        if (!$eventIds) return;

        $reminderMetas = [];

        $rows = Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->byKey(self::META_KEY)
            ->whereIn('object_id', $eventIds)
            ->get();

        foreach ($rows as $row) {
            $reminderMetas[$row->object_id] = $row;
        }

        foreach ($eventIds as $eventId) {
            $eventId = (int) $eventId;
            $reminderMeta = $reminderMetas[$eventId] ?? null;

            if (!$reminderMeta) {
                Meta::query()->create([
                    'object_type' => Meta::OBJECT_TYPE_EVENT,
                    'object_id'   => $eventId,
                    'meta_key'    => self::META_KEY,
                    'value'       => ['status' => 'scheduled', 'cursor' => 0],
                ]);

                as_schedule_single_action(time(), 'fluent_event/send_event_reminders_to_guests', [$eventId], 'fluent-event');
                continue;
            }

            if (
                ($reminderMeta->value['status'] ?? '') === 'scheduled'
                && !as_next_scheduled_action('fluent_event/send_event_reminders_to_guests', [$eventId], 'fluent-event')
            ) {
                as_schedule_single_action(time(), 'fluent_event/send_event_reminders_to_guests', [$eventId], 'fluent-event');
            }
        }
    }

    public static function sendReminders($eventId)
    {
        $reminderMeta = Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('object_id', $eventId)
            ->byKey(self::META_KEY)
            ->first();

        if (!$reminderMeta || ($reminderMeta->value['status'] ?? '') !== 'scheduled') return;

        $event = Event::query()->find($eventId);

        if (
            !$event
            || $event->status !== 'public'
            || $event->start_at <= gmdate(DateTimeHelper::FORMAT)
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
                    as_schedule_single_action(time(), 'fluent_event/send_event_reminders_to_guests', [(int) $event->id], 'fluent-event');
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
