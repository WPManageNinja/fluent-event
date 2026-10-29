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

    // One send per reminder time, queued for start - lead time. Skips when the queued set already
    // matches; otherwise requeues it all. Sent or mid-send ones stay put unless $resend (the start moved).
    public static function scheduleReminders($event, $resend = false)
    {
        $eventId = (int) $event->id;
        $startsAt = strtotime($event->start_at . ' UTC');
        $leadTimes = [];

        if (in_array($event->status, ['public', 'private'], true)) {
            foreach (self::leadTimes(self::reminderFor($eventId)) as $leadTime) {
                if ($startsAt - $leadTime > time()) {
                    $leadTimes[] = $leadTime;
                }
            }
        }

        $reminderMetas = self::reminderMetas($eventId);
        $queued = [];

        foreach ($reminderMetas as $leadTime => $reminderMeta) {
            if (($reminderMeta->value['status'] ?? '') !== 'scheduled') {
                continue;
            }

            // Deactivation cancels every queued send but leaves the meta saying scheduled.
            $hasAction = as_has_scheduled_action('fluent_event/send_event_reminders_to_guests', [$eventId, $leadTime], 'fluent-event');

            if (empty($reminderMeta->value['cursor'])) {
                if ($hasAction) {
                    $queued[] = $leadTime;
                } else {
                    $reminderMeta->delete();
                    unset($reminderMetas[$leadTime]);
                }
            } elseif (!$hasAction && !$resend) {
                // Half sent: resume from the saved cursor.
                as_schedule_single_action(time(), 'fluent_event/send_event_reminders_to_guests', [$eventId, $leadTime], 'fluent-event');
            }
        }

        sort($queued);

        if (!$resend && $queued === $leadTimes) {
            return;
        }

        foreach ($reminderMetas as $leadTime => $reminderMeta) {
            if (!$resend && !in_array($leadTime, $queued, true)) {
                $leadTimes = array_diff($leadTimes, [$leadTime]);
                continue;
            }

            as_unschedule_all_actions('fluent_event/send_event_reminders_to_guests', [$eventId, $leadTime], 'fluent-event');
            $reminderMeta->delete();
        }

        foreach ($leadTimes as $leadTime) {
            Meta::query()->create([
                'object_type' => Meta::OBJECT_TYPE_EVENT,
                'object_id'   => $eventId,
                'meta_key'    => self::META_KEY . ':' . $leadTime,
                'value'       => ['status' => 'scheduled', 'cursor' => 0],
            ]);

            as_schedule_single_action($startsAt - $leadTime, 'fluent_event/send_event_reminders_to_guests', [$eventId, $leadTime], 'fluent-event');
        }
    }

    // A new start time owes every reminder again, including ones already sent for the old time.
    public static function rescheduleReminders($event)
    {
        self::scheduleReminders($event, $event->wasChanged('start_at'));
    }

    public static function rescheduleAllReminders()
    {
        as_enqueue_async_action('fluent_event/reschedule_event_reminders', [0], 'fluent-event');
    }

    public static function cancelReminders($event)
    {
        foreach (array_keys(self::reminderMetas($event->id)) as $leadTime) {
            as_unschedule_all_actions('fluent_event/send_event_reminders_to_guests', [(int) $event->id, $leadTime], 'fluent-event');
        }
    }

    // Requeues every upcoming event in batches, each batch its own job.
    public static function rescheduleUpcoming($afterId = 0)
    {
        $events = Event::query()
            ->select(['id', 'status', 'start_at'])
            ->whereIn('status', ['public', 'private'])
            ->where('start_at', '>', gmdate(DateTimeHelper::FORMAT))
            ->where('id', '>', (int) $afterId)
            ->orderBy('id', 'ASC')
            ->limit(self::BATCH_SIZE)
            ->get();

        foreach ($events as $event) {
            self::scheduleReminders($event);
        }

        if (count($events) === self::BATCH_SIZE) {
            as_enqueue_async_action('fluent_event/reschedule_event_reminders', [(int) $events->last()->id], 'fluent-event');
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
            || !in_array($event->status, ['public', 'private'], true)
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
                ->whereIn('status', Event::SEAT_STATUSES)
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

    public static function sendWaitlist($guestId)
    {
        if (empty(Settings::get(Settings::EMAIL_NOTIFICATIONS)['registration_waitlist']['enabled'])) return;

        $guest = Guest::query()->find((int) $guestId);

        if (!$guest || $guest->status !== 'waitlisted' || !is_email($guest->email)) return;

        $event = Event::query()->find($guest->event_id);

        if (!$event || $event->status === 'draft') return;

        $subject = sprintf(
            /* translators: %s is the event title */
            __('You are on the waitlist for %s', 'fluent-event'),
            $event->title
        );

        /* translators: 1: event title, 2: formatted start date/time, 3: timezone */
        $intro = __('You are on the waitlist for %1$s on %2$s (%3$s). The event is full, so you do not have a seat yet.', 'fluent-event');

        // A waitlisted guest has no seat, so like a cancellation it stops before the meeting link.
        Mailer::send($guest->email, $subject, self::buildGreeting($event, $guest, $intro));

        do_action('fluent_event/guest/waitlist_sent', $guest, $event);
    }

    // Sorted, so scheduleReminders() can compare it to what is queued; empty when the reminder is turned off.
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

    // Keyed by lead time in seconds.
    protected static function reminderMetas($eventId)
    {
        $reminderMetas = [];

        $rows = Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('object_id', $eventId)
            ->where('meta_key', 'like', self::META_KEY . ':%')
            ->get();

        foreach ($rows as $row) {
            $reminderMetas[(int) substr($row->meta_key, strlen(self::META_KEY) + 1)] = $row;
        }

        return $reminderMetas;
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
            $place = array_filter([
                $meta['venue'] ?? '',
                $meta['address'] ?? '',
                trim(($meta['city'] ?? '') . ' ' . ($meta['zip_code'] ?? '')),
                $meta['country'] ?? '',
            ]);

            $body .= '<p>' . esc_html(implode(', ', $place)) . '</p>';

            if (!empty($meta['map_url'])) {
                $body .= '<p><a href="' . esc_url($meta['map_url']) . '">' . esc_html__('View map', 'fluent-event') . '</a></p>';
            }
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
