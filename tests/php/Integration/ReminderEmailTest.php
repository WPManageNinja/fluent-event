<?php

namespace FluentEvent\Tests\Integration;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Meta;
use FluentEvent\App\Services\EventReminderService;
use FluentEvent\App\Services\Settings;
use FluentEvent\Tests\IntegrationTestCase;

/**
 * Reminders are queued per (event, lead time) with Action Scheduler, and every
 * guest email goes through Mailer. Action Scheduler rows live in the same
 * database, so the rollback takes them back too.
 */
class ReminderEmailTest extends IntegrationTestCase
{
    const SEND_HOOK = 'fluent_event/send_event_reminders_to_guests';
    const DAY = 86400;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actAs($this->createAdmin());
    }

    private function reminderMeta($eventId, $leadTime)
    {
        return Meta::query()
            ->where('object_type', Meta::OBJECT_TYPE_EVENT)
            ->where('object_id', $eventId)
            ->byKey(EventReminderService::META_KEY . ':' . $leadTime)
            ->first();
    }

    private function skipIfSendWouldYield()
    {
        // sendReminders() hands off to a new job once the process is MAX_RUN_TIME old, which a long run can reach.
        if (microtime(true) - FLUENT_EVENT_START_TIME > EventReminderService::MAX_RUN_TIME - 5) {
            $this->markTestSkipped('This process is too old for sendReminders() to run inline; run this class on its own.');
        }
    }

    public function test_a_public_upcoming_event_queues_one_send_per_lead_time()
    {
        $event = $this->createEvent();

        EventReminderService::scheduleReminders($event);

        $this->assertTrue($this->hasQueuedAction(self::SEND_HOOK, [(int) $event->id, self::DAY]));
        $this->assertSame('scheduled', $this->reminderMeta($event->id, self::DAY)->value['status']);
    }

    public function test_an_unlisted_event_still_queues_its_reminders()
    {
        $event = $this->createEvent(['status' => 'unlisted']);

        EventReminderService::scheduleReminders($event);

        $this->assertTrue($this->hasQueuedAction(self::SEND_HOOK, [(int) $event->id, self::DAY]));
    }

    public function test_scheduling_twice_does_not_queue_a_duplicate()
    {
        $event = $this->createEvent();

        EventReminderService::scheduleReminders($event);
        EventReminderService::scheduleReminders($event);

        $actions = as_get_scheduled_actions([
            'hook'   => self::SEND_HOOK,
            'args'   => [(int) $event->id, self::DAY],
            'group'  => 'fluent-event',
            'status' => \ActionScheduler_Store::STATUS_PENDING,
        ], 'ids');

        $this->assertCount(1, $actions);
    }

    public function test_a_draft_event_queues_nothing()
    {
        $event = $this->createEvent(['status' => 'draft']);

        EventReminderService::scheduleReminders($event);

        $this->assertFalse($this->hasQueuedAction(self::SEND_HOOK, [(int) $event->id, self::DAY]));
    }

    public function test_a_lead_time_that_has_already_passed_is_skipped()
    {
        $event = $this->createEvent([
            'start_at' => gmdate('Y-m-d H:i:s', strtotime('+2 hours')),
            'end_at'   => gmdate('Y-m-d H:i:s', strtotime('+3 hours')),
        ]);

        EventReminderService::scheduleReminders($event);

        $this->assertFalse($this->hasQueuedAction(self::SEND_HOOK, [(int) $event->id, self::DAY]));
    }

    // WP-Cron needs traffic, so a send can still be pending after its time; saving the event must not drop it.
    public function test_an_overdue_send_still_pending_survives_saving_the_event()
    {
        $threeHours = 3 * HOUR_IN_SECONDS;
        Settings::update(Settings::EMAIL_NOTIFICATIONS, array_merge(
            Settings::DEFAULTS[Settings::EMAIL_NOTIFICATIONS],
            ['reminder_to_guest' => ['enabled' => true, 'times' => [['value' => 3, 'unit' => 'hours']]]]
        ));
        $event = $this->createEvent([
            'start_at' => gmdate('Y-m-d H:i:s', strtotime('+2 hours')),
            'end_at'   => gmdate('Y-m-d H:i:s', strtotime('+3 hours')),
        ]);
        Meta::query()->create([
            'object_type' => Meta::OBJECT_TYPE_EVENT,
            'object_id'   => $event->id,
            'meta_key'    => EventReminderService::META_KEY . ':' . $threeHours,
            'value'       => ['status' => 'scheduled', 'cursor' => 0],
        ]);
        as_schedule_single_action(time() - HOUR_IN_SECONDS, self::SEND_HOOK, [(int) $event->id, $threeHours], 'fluent-event');

        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, ['title' => 'Typo fixed']));

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertTrue($this->hasQueuedAction(self::SEND_HOOK, [(int) $event->id, $threeHours]));
        $this->assertSame('scheduled', $this->reminderMeta($event->id, $threeHours)->value['status']);
    }

    public function test_moving_the_start_requeues_the_reminder_for_the_new_time()
    {
        $event = $this->createEvent();
        EventReminderService::scheduleReminders($event);

        $newStart = gmdate('Y-m-d H:i:s', strtotime('+10 days'));
        $response = $this->rest('PATCH', "events/{$event->id}", $this->eventPayload($event, [
            'start_at' => $newStart,
            'end_at'   => gmdate('Y-m-d H:i:s', strtotime('+10 days +2 hours')),
        ]));

        $this->assertSame(200, $response['status'], wp_json_encode($response['data']));
        $this->assertSame(
            strtotime($newStart . ' UTC') - self::DAY,
            as_next_scheduled_action(self::SEND_HOOK, [(int) $event->id, self::DAY], 'fluent-event')
        );
    }

    public function test_deleting_an_event_cancels_its_queued_reminders()
    {
        $event = $this->createEvent();
        EventReminderService::scheduleReminders($event);

        $this->assertSame(200, $this->rest('DELETE', "events/{$event->id}")['status']);
        $this->assertFalse($this->hasQueuedAction(self::SEND_HOOK, [(int) $event->id, self::DAY]));
    }

    public function test_deleting_an_event_post_outside_the_plugin_cancels_its_queued_reminders()
    {
        $event = $this->createEvent();
        EventReminderService::scheduleReminders($event);

        wp_delete_post($event->post_id, true);

        $this->assertFalse($this->hasQueuedAction(self::SEND_HOOK, [(int) $event->id, self::DAY]));
    }

    public function test_a_reminder_goes_to_going_and_checked_in_guests_only()
    {
        $this->skipIfSendWouldYield();

        $event = $this->createEvent();
        $going = $this->createGuest($event, ['status' => 'going']);
        $checkedIn = $this->createGuest($event, ['status' => 'checked_in']);
        $this->createGuest($event, ['status' => 'not_going']);
        $this->createGuest($event, ['status' => 'cancelled']);
        $this->createGuest($event, ['status' => 'waitlisted']);

        EventReminderService::scheduleReminders($event);
        EventReminderService::sendReminders($event->id, self::DAY);

        $this->assertEqualsCanonicalizing([$going->email, $checkedIn->email], array_column($this->sentMail, 'to'));
        $this->assertSame('completed', $this->reminderMeta($event->id, self::DAY)->value['status']);
    }

    public function test_a_reminder_for_an_event_switched_to_draft_is_skipped()
    {
        $this->skipIfSendWouldYield();

        $event = $this->createEvent();
        $this->createGuest($event);
        EventReminderService::scheduleReminders($event);

        Event::query()->where('id', $event->id)->update(['status' => 'draft']);
        EventReminderService::sendReminders($event->id, self::DAY);

        $this->assertNoMailSent();
        $this->assertSame('skipped', $this->reminderMeta($event->id, self::DAY)->value['status']);
    }

    public function test_an_online_reminder_carries_the_meeting_link()
    {
        $this->skipIfSendWouldYield();

        $event = $this->createEvent(['venue_type' => 'online'], ['online_meeting_url' => 'https://meet.example.test/launch']);
        $this->createGuest($event);
        EventReminderService::scheduleReminders($event);

        EventReminderService::sendReminders($event->id, self::DAY);

        $this->assertCount(1, $this->sentMail);
        $this->assertStringContainsString('https://meet.example.test/launch', $this->sentMail[0]['message']);
    }

    public function test_a_confirmation_goes_to_a_going_guest()
    {
        $guest = $this->createGuest($this->createEvent());

        EventReminderService::sendConfirmation($guest->id);

        $this->assertMailSentTo($guest->email);
    }

    public function test_no_confirmation_when_confirmations_are_switched_off()
    {
        $guest = $this->createGuest($this->createEvent());
        Settings::update(Settings::EMAIL_NOTIFICATIONS, array_merge(
            Settings::DEFAULTS[Settings::EMAIL_NOTIFICATIONS],
            ['registration_confirmation' => ['enabled' => false]]
        ));

        EventReminderService::sendConfirmation($guest->id);

        $this->assertNoMailSent();
    }

    public function test_no_confirmation_for_a_guest_who_is_not_going_or_a_draft_event()
    {
        $notGoing = $this->createGuest($this->createEvent(), ['status' => 'not_going']);
        $draftGuest = $this->createGuest($this->createEvent(['status' => 'draft']));

        EventReminderService::sendConfirmation($notGoing->id);
        EventReminderService::sendConfirmation($draftGuest->id);

        $this->assertNoMailSent();
    }

    public function test_a_waitlisted_guest_is_told_without_the_meeting_link()
    {
        $event = $this->createEvent(['venue_type' => 'online'], ['online_meeting_url' => 'https://meet.example.test/secret']);
        $guest = $this->createGuest($event, ['status' => 'waitlisted']);

        EventReminderService::sendWaitlist($guest->id);

        $this->assertMailSentTo($guest->email);
        $this->assertStringContainsString('waitlist', $this->sentMail[0]['subject']);
        $this->assertStringNotContainsString('meet.example.test', $this->sentMail[0]['message']);
    }

    public function test_no_waitlist_email_when_switched_off_or_the_guest_already_has_a_seat()
    {
        $promoted = $this->createGuest($this->createEvent(), ['status' => 'going']);
        EventReminderService::sendWaitlist($promoted->id);

        $waitlisted = $this->createGuest($this->createEvent(), ['status' => 'waitlisted']);
        Settings::update(Settings::EMAIL_NOTIFICATIONS, array_merge(
            Settings::DEFAULTS[Settings::EMAIL_NOTIFICATIONS],
            ['registration_waitlist' => ['enabled' => false]]
        ));
        EventReminderService::sendWaitlist($waitlisted->id);

        $this->assertNoMailSent();
    }

    public function test_a_from_address_with_an_injected_header_never_reaches_the_mail_headers()
    {
        $headers = null;
        $this->addTestFilter('pre_wp_mail', function ($return, $atts) use (&$headers) {
            $headers = $atts['headers'];
            return $return;
        }, 10, 2);

        Settings::update(Settings::EMAIL, [
            'from_name'  => "Events\r\nBcc: spy@evil.test",
            'from_email' => 'events@example.test',
        ]);

        EventReminderService::sendConfirmation($this->createGuest($this->createEvent())->id);

        $this->assertNotNull($headers, 'No mail was attempted');

        // The text may survive inside the From name; what must not exist is a line of its own.
        foreach ((array) $headers as $header) {
            $this->assertDoesNotMatchRegularExpression('/[\r\n]/', $header);
            $this->assertDoesNotMatchRegularExpression('/^\s*bcc\s*:/i', $header);
        }
    }
}
