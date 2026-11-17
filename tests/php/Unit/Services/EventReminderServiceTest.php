<?php

namespace FluentEvent\Tests\Unit\Services;

use FluentEvent\App\Services\EventReminderService;
use FluentEvent\Tests\TestCase;

/**
 * The pure parts of reminder scheduling and the email bodies. Queueing against
 * Action Scheduler and the guest fan-out run in the integration tier.
 */
class EventReminderServiceTest extends TestCase
{
    private function leadTimes(array $reminder)
    {
        return $this->callProtected(EventReminderService::class, 'leadTimes', [$reminder]);
    }

    private function event(array $overrides = [])
    {
        return (object) array_merge([
            'title'          => 'Launch party',
            'timezone'       => 'Asia/Dhaka',
            'start_at_local' => '2026-11-01 18:00:00',
            'venue_type'     => 'online',
            'meta'           => [
                'online_meeting_url' => 'https://meet.example.com/launch',
                'venue'              => '',
                'address'            => '',
            ],
        ], $overrides);
    }

    public function test_a_disabled_reminder_has_no_lead_times()
    {
        $this->assertSame([], $this->leadTimes(['enabled' => false, 'times' => [['value' => 1, 'unit' => 'hours']]]));
    }

    public function test_lead_times_are_deduplicated_sorted_and_drop_invalid_entries()
    {
        // scheduleReminders() compares this list to what is queued, so order and uniqueness matter.
        $leadTimes = $this->leadTimes(['enabled' => true, 'times' => [
            ['value' => 24, 'unit' => 'hours'],
            ['value' => 1, 'unit' => 'days'],
            ['value' => 30, 'unit' => 'minutes'],
            ['value' => 0, 'unit' => 'hours'],
            ['value' => 2, 'unit' => 'weeks'],
        ]]);

        $this->assertSame([1800, 86400], $leadTimes);
    }

    public function test_a_reminder_for_an_online_event_carries_the_meeting_link()
    {
        $body = $this->callProtected(EventReminderService::class, 'buildBody', [$this->event(), (object) ['name' => 'Rafi'], '%1$s on %2$s (%3$s)']);

        $this->assertStringContainsString('https://meet.example.com/launch', $body);
        $this->assertStringContainsString('Hi Rafi,', $body);
        $this->assertStringContainsString('Launch party', $body);
    }

    public function test_a_reminder_for_an_offline_event_gives_the_venue_and_address()
    {
        $event = $this->event([
            'venue_type' => 'offline',
            'meta'       => ['online_meeting_url' => 'https://meet.example.com/stale', 'venue' => 'Hall A', 'address' => '12 Road'],
        ]);

        $body = $this->callProtected(EventReminderService::class, 'buildBody', [$event, (object) ['name' => 'Rafi'], '%1$s']);

        $this->assertStringContainsString('Hall A 12 Road', $body);
        $this->assertStringNotContainsString('meet.example.com', $body);
    }

    public function test_a_cancellation_never_carries_the_meeting_link()
    {
        $greeting = $this->callProtected(EventReminderService::class, 'buildGreeting', [$this->event(), (object) ['name' => 'Rafi'], '%1$s on %2$s (%3$s)']);

        $this->assertStringNotContainsString('meet.example.com', $greeting);
        $this->assertStringContainsString('Asia/Dhaka', $greeting);
    }

    public function test_each_guest_email_has_its_own_subject_and_only_the_cancellation_drops_the_link()
    {
        $expected = [
            'confirmation' => ['You are registered for Launch party', true],
            'cancellation' => ['Your registration for Launch party is cancelled', false],
            'reminder'     => ['Reminder: Launch party is coming up', true],
        ];

        foreach ($expected as $type => [$subject, $hasLink]) {
            $email = EventReminderService::buildEmail($type, $this->event(), (object) ['name' => 'Rafi']);

            $this->assertSame($subject, $email['subject'], $type);
            $this->assertSame($hasLink, strpos($email['body'], 'meet.example.com') !== false, $type);
        }
    }

    /**
     * @dataProvider greetedGuests
     */
    public function test_only_a_visitor_registration_is_greeted_without_the_typed_name($guest, $expected)
    {
        $greeting = $this->callProtected(EventReminderService::class, 'buildGreeting', [$this->event(), (object) $guest, '%1$s']);

        $this->assertStringContainsString($expected, $greeting);
    }

    public function greetedGuests()
    {
        return [
            'visitor on a page'      => [['name' => 'Click evil.example', 'user_id' => null, 'source' => 'wordpress_pages'], 'Hi there,'],
            'visitor in a community' => [['name' => 'Click evil.example', 'user_id' => null, 'source' => 'fluent_community'], 'Hi there,'],
            'member'                 => [['name' => 'Rafi', 'user_id' => 7, 'source' => 'fluent_community'], 'Hi Rafi,'],
            'added by an admin'      => [['name' => 'Rafi', 'user_id' => null, 'source' => 'manual'], 'Hi Rafi,'],
        ];
    }

    public function test_a_guest_without_a_name_is_greeted_generically()
    {
        $greeting = $this->callProtected(EventReminderService::class, 'buildGreeting', [$this->event(), (object) ['name' => ''], '%1$s']);

        $this->assertStringContainsString('Hi there,', $greeting);
    }
}
