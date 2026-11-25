<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\App;
use FluentEvent\App\CPT\EventPost;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Utils\DateTimeHelper;

/**
 * The public event page's side card — when, status, registration, where, how to join, add to
 * calendar — rendered for the page and again by the registration endpoints after each change.
 * Its date labels and calendar file come from DateTimeHelper.
 */
class EventPageService
{
    // The settings "+" creates these. The content is ours, never a request's.
    const PAGES = [
        'events_page_id' => ['shortcode' => 'fluent_events', 'block' => 'fluent-event/events'],
    ];

    // A setting's page while it is published, else 0: a trashed or draft page turns its links off.
    public static function pageId($key)
    {
        $id = (int) (Settings::get()[$key] ?? 0);

        return $id && get_post_type($id) === 'page' && get_post_status($id) === 'publish' ? $id : 0;
    }

    // The All Events Page, else the events archive, which lists them itself.
    public static function listUrl()
    {
        $id = self::pageId('events_page_id');

        return (string) ($id ? get_permalink($id) : get_post_type_archive_link(EventPost::POST_TYPE));
    }

    public static function url(Event $event)
    {
        return (string) get_permalink($event->post_id);
    }

    public static function createPage($key, $title)
    {
        $id = wp_insert_post([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'post_title'     => $title,
            // The block, not the shortcode: the editor shows it with its settings, aligned wide like the list's frame.
            'post_content'   => '<!-- wp:' . self::PAGES[$key]['block'] . ' {"align":"wide"} /-->',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ]);

        if (!$id || is_wp_error($id)) {
            return 0;
        }

        Settings::update(Settings::GENERAL, [$key => (int) $id]);

        return (int) $id;
    }

    /**
     * @param array  $formatted EventService::formatEvent() shape, with a logged-in viewer's own status.
     * @param string $url       The event page.
     */
    // $answer draws the popup the page opens after a registration: 'registered' confirms it; 'already_registered',
    // 'cancelled' and 'checked_in' warn why it was refused.
    public static function sideCard(Event $event, array $formatted, $url, $answer = '')
    {
        $siteZone = wp_timezone_string();
        $isOnline = $event->venue_type === 'online';
        $start = strtotime($event->start_at . ' UTC');
        $end = strtotime($event->end_at . ' UTC');
        $phase = time() >= $end ? 'ended' : (time() >= $start ? 'live' : 'upcoming');
        $isSeated = in_array($formatted['my_status'], Event::SEAT_STATUSES, true);
        $meetingLink = preg_match('#^https?://#i', $formatted['online_meeting_url']) ? $formatted['online_meeting_url'] : '';
        $isFull = $formatted['is_full'] && !$isSeated;
        $meter = null;
        $left = (int) $formatted['spots_left'];
        /* translators: %s: number of spots left */
        $spotsLabel = $left > 0 && $phase !== 'ended' ? sprintf(_n('%s spot left', '%s spots left', $left, 'fluent-event'), number_format_i18n($left)) : '';

        // With both the count and the spots left shown; an ended one keeps it as a record of how full it got.
        if ($formatted['guest_count'] !== null && $formatted['spots_left'] !== null) {
            $meter = [
                'percent' => min(100, (int) round($formatted['guest_count'] / $formatted['capacity'] * 100)),
                'status'  => $phase === 'ended' || $isFull ? '' : ($spotsLabel ?: __('Event full', 'fluent-event')),
            ];
        }

        $data = [
            'event'            => $formatted,
            'is_online'        => $isOnline,
            'chip_month'       => DateTimeHelper::wallClock($event->start_at_local, 'M'),
            'chip_day'         => DateTimeHelper::wallClock($event->start_at_local, 'j'),
            'date_label'       => DateTimeHelper::dateRange($event->start_at_local, $event->end_at_local),
            'time_label'       => DateTimeHelper::timeRange($event->start_at_local, $event->end_at_local, $event->timezone),
            // The site's clock under the event's own, only when the two read differently.
            'local_time_label' => $event->start_at_site === $event->start_at_local && $event->end_at_site === $event->end_at_local
                ? ''
                : DateTimeHelper::dateTimeRange($event->start_at_site, $event->end_at_site, $siteZone),
            'phase'            => $phase,
            /* translators: %s: time until the event starts, e.g. "3 days" */
            'status_label'     => $phase === 'ended' ? __('Ended', 'fluent-event') : sprintf(__('Starts in %s', 'fluent-event'), human_time_diff(time(), $start)),
            // The start as a Unix timestamp when the host chose a countdown; portal.js ticks it down.
            'countdown_to'     => $phase === 'upcoming' && $formatted['start_display'] === 'countdown' ? $start : null,
            'ends_at'          => $end,
            // The going count, or the spots left when only those show.
            /* translators: %s: number of guests going */
            'count_label'      => $formatted['guest_count'] === null ? $spotsLabel : sprintf(__('%s going', 'fluent-event'), number_format_i18n($formatted['guest_count'])),
            'meter'            => $meter,
            'notice'           => null,
            'meeting_link'     => $meetingLink,
            'registration'     => null,
            'add_to_calendar'  => null,
            'answer'           => $answer,
        ];

        if ($phase !== 'ended' && in_array($formatted['entry_type'], ['rsvp', 'open'], true)) {
            if ($isFull) {
                $data['notice'] = [
                    'type'  => 'full',
                    'title' => __('Event full', 'fluent-event'),
                    'text'  => __('This event is full and no longer taking registrations.', 'fluent-event'),
                ];
            } else {
                $data['registration'] = self::registration($event, $formatted['my_status'], $isSeated);
            }
        }

        // Once someone is going, or for a no-entry event anyone can attend.
        if ($phase !== 'ended' && ($formatted['entry_type'] === 'none' || $isSeated)) {
            $place = $isOnline ? ($meetingLink ?: $url) : implode(', ', array_filter([$formatted['venue'], $formatted['address']]));

            // Plain text for calendar apps; block tags become line breaks so words don't run together.
            $text = preg_replace(['/<li[^>]*>/i', '/<\/(p|h[1-6]|li|div)>|<br\s*\/?>/i'], ['• ', "\n"], $event->description);
            $text = trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES, 'UTF-8')));
            $summary = mb_substr($text, 0, 600) . "\n\n" . $url;
            $dates = [gmdate('Ymd\THis\Z', $start), gmdate('Ymd\THis\Z', $end)];

            $data['add_to_calendar'] = [
                'google'   => 'https://calendar.google.com/calendar/render?' . http_build_query([
                    'action'   => 'TEMPLATE',
                    'text'     => $event->title,
                    'dates'    => implode('/', $dates),
                    'details'  => $summary,
                    'location' => $place,
                ], '', '&', PHP_QUERY_RFC3986),
                'ics'      => 'data:text/calendar;charset=utf-8,' . rawurlencode(DateTimeHelper::icsFile([
                    'id'          => $event->id,
                    'title'       => $event->title,
                    'start_at'    => $event->start_at,
                    'end_at'      => $event->end_at,
                    'description' => $summary,
                    'location'    => $place,
                    'url'         => $url,
                ])),
                'filename' => $event->post->post_name . '.ics',
            ];
        }

        return (string) App::make('view')->make('portal.event-side', $data);
    }

    // Every post carries the event's own nonce, so only its page can register; WordPress binds it to a logged-in
    // viewer's session, so theirs also carries the REST cookie nonce that lets it verify. A member answers by account,
    // like on the portal: their Going / Not going posts to member_url, and the form comes prefilled.
    protected static function registration(Event $event, $myStatus, $isSeated)
    {
        $config = App::make('config');
        $base = rest_url($config->get('app.rest_namespace') . '/' . $config->get('app.rest_version') . '/portal/events/' . (int) $event->id);
        $user = wp_get_current_user();

        return [
            'is_going'    => $isSeated,
            'is_member'   => $user->exists(),
            'status'      => $isSeated ? 'going' : ($myStatus === 'not_going' ? 'not_going' : ''),
            'name'        => $user->exists() ? $user->display_name : '',
            'email'       => $user->exists() ? $user->user_email : '',
            'url'         => $base . '/register-guest',
            'member_url'  => $user->exists() ? $base . '/register' : '',
            'event_nonce' => wp_create_nonce('fevent_portal_register_' . $event->id),
            'rest_nonce'  => $user->exists() ? wp_create_nonce('wp_rest') : '',
        ];
    }
}
