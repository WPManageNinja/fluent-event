<?php

namespace FluentEvent\App\Services;

use FluentEvent\App\App;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Utils\DateTimeHelper;

/**
 * The public event page's side card — when, status, registration, where, how to join, add to
 * calendar — rendered for the page and again by the registration endpoints after each change.
 * Its date labels and calendar file come from DateTimeHelper.
 */
class EventPageService
{
    // Under its calendar; the calendar relation must be loaded.
    public static function url(Event $event)
    {
        return home_url(user_trailingslashit(Settings::portalSlug() . '/' . $event->calendar->slug . '/' . $event->post->post_name));
    }

    /**
     * @param array  $formatted EventService::formatEvent() shape, for this viewer.
     * @param string $url       The event page.
     */
    public static function sideCard(Event $event, array $formatted, $url)
    {
        $siteZone = wp_timezone_string();
        $isOnline = $event->venue_type === 'online';
        $start = strtotime($event->start_at . ' UTC');
        $end = strtotime($event->end_at . ' UTC');
        $phase = time() >= $end ? 'ended' : (time() >= $start ? 'live' : 'upcoming');
        $isSeated = in_array($formatted['my_status'], Event::SEAT_STATUSES, true);
        $meetingLink = preg_match('#^https?://#i', $formatted['online_meeting_url']) ? $formatted['online_meeting_url'] : '';

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
            /* translators: %s: number of guests going */
            'count_label'      => $formatted['guest_count'] === null ? '' : sprintf(__('%s going', 'fluent-event'), number_format_i18n($formatted['guest_count'])),
            'meeting_link'     => $meetingLink,
            'registration'     => null,
            'add_to_calendar'  => null,
        ];

        if ($phase !== 'ended' && in_array($formatted['entry_type'], ['rsvp', 'open'], true)) {
            $data['registration'] = self::registration($event, $formatted, $isSeated);
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

    // Everyone posts the event's own nonce, so only its page can register; members add their REST cookie nonce.
    protected static function registration(Event $event, array $formatted, $isSeated)
    {
        $user = wp_get_current_user();
        $config = App::make('config');
        $base = rest_url($config->get('app.rest_namespace') . '/' . $config->get('app.rest_version') . '/portal/events/' . (int) $event->id);

        return [
            'is_member'     => $user->exists(),
            'status'        => $isSeated ? 'going' : ($formatted['my_status'] === 'not_going' ? 'not_going' : ''),
            'name'          => $user->exists() ? $user->display_name : '',
            'email'         => $user->exists() ? $user->user_email : '',
            'member_url'    => $base . '/register',
            'visitor_url'   => $base . '/register-guest',
            'rest_nonce'    => $user->exists() ? wp_create_nonce('wp_rest') : '',
            'event_nonce'   => wp_create_nonce('fe_portal_register_' . $event->id),
        ];
    }
}
