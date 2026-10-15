<?php

namespace FluentEvent\Tests\Unit\Modules;

use FluentEvent\Modules\FluentCommunity\CommunityEvents;
use FluentEvent\Tests\TestCase;

/**
 * What the portal shows a member about an event. The meeting-link rule is the
 * one that leaks if it slips: an RSVP event's link is for its guests and staff,
 * an open event shows it to every viewer (CLAUDE.md, entry modes).
 */
class CommunityEventFormatTest extends TestCase
{
    private function event(array $overrides = [], array $meta = [])
    {
        return (object) array_merge([
            'id'             => 12,
            'title'          => 'Launch party',
            'description'    => '<p>Come along</p>',
            'status'         => 'public',
            'start_at'       => gmdate('Y-m-d H:i:s', strtotime('+1 day')),
            'end_at'         => gmdate('Y-m-d H:i:s', strtotime('+1 day +2 hours')),
            'start_at_local' => '',
            'end_at_local'   => '',
            'timezone'       => 'UTC',
            'venue_type'     => 'online',
            'meta'           => array_merge([
                'entry_type'         => 'rsvp',
                'online_meeting_url' => 'https://meet.example.com/launch',
                'show_guest_count'   => 'yes',
            ], $meta),
        ], $overrides);
    }

    private function format($event, $seats = 0, $myStatus = null, $isStaff = false, $canEdit = false)
    {
        return CommunityEvents::formatEvent($event, $seats, $myStatus, $isStaff, $canEdit);
    }

    public function test_an_rsvp_link_is_hidden_from_a_member_who_has_not_registered()
    {
        $formatted = $this->format($this->event());

        $this->assertSame('', $formatted['online_meeting_url']);
        $this->assertTrue($formatted['has_meeting_url'], 'The card should still say a link exists');
    }

    public function test_an_rsvp_link_is_hidden_from_a_member_who_said_not_going()
    {
        $this->assertSame('', $this->format($this->event(), 0, 'not_going')['online_meeting_url']);
    }

    /**
     * @dataProvider seatStatuses
     */
    public function test_an_rsvp_link_is_shown_to_a_registered_guest($status)
    {
        $this->assertSame('https://meet.example.com/launch', $this->format($this->event(), 1, $status)['online_meeting_url']);
    }

    public function seatStatuses()
    {
        return ['going' => ['going'], 'checked in' => ['checked_in']];
    }

    public function test_an_rsvp_link_is_shown_to_staff()
    {
        $this->assertSame('https://meet.example.com/launch', $this->format($this->event(), 0, null, true)['online_meeting_url']);
    }

    public function test_an_open_event_shows_its_link_to_every_viewer()
    {
        $formatted = $this->format($this->event([], ['entry_type' => 'open']));

        $this->assertSame('https://meet.example.com/launch', $formatted['online_meeting_url']);
        $this->assertFalse($formatted['registration_open'], 'An open event takes no registrations');
    }

    public function test_an_offline_event_never_exposes_a_stale_meeting_link()
    {
        $formatted = $this->format($this->event(['venue_type' => 'offline'], ['entry_type' => 'open']));

        $this->assertFalse($formatted['has_meeting_url']);
        $this->assertSame('', $formatted['online_meeting_url']);
    }

    public function test_the_guest_count_shows_only_for_rsvp_events_that_allow_it()
    {
        $this->assertSame(5, $this->format($this->event(), 5)['guest_count']);
        $this->assertNull($this->format($this->event([], ['show_guest_count' => 'no']), 5)['guest_count']);
        $this->assertNull($this->format($this->event([], ['entry_type' => 'open']), 5)['guest_count']);
    }

    public function test_registration_closes_once_the_event_has_ended()
    {
        $past = $this->event(['end_at' => gmdate('Y-m-d H:i:s', strtotime('-1 hour'))]);

        $formatted = $this->format($past);

        $this->assertTrue($formatted['is_past']);
        $this->assertFalse($formatted['registration_open']);
        $this->assertTrue($this->format($this->event())['registration_open']);
    }

    public function test_only_an_editor_gets_the_edit_link()
    {
        $this->assertSame('', $this->format($this->event())['edit_url']);
        $this->assertStringContainsString('#/events/12/edit', $this->format($this->event(), 0, null, false, true)['edit_url']);
    }

    public function test_missing_meta_keys_fall_back_to_defaults()
    {
        $formatted = $this->format($this->event(['meta' => null]));

        $this->assertSame('', $formatted['venue']);
        $this->assertSame('', $formatted['cover_url']);
    }

    public function test_drafts_never_reach_the_portal()
    {
        $this->assertSame([], CommunityEvents::formatEvents([$this->event(['status' => 'draft']), null]));
    }
}
