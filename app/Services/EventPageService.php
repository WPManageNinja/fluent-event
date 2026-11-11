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
    // The detail page reads its event from this query var; the rewrite rule fills it from /event/<slug>.
    const EVENT_VAR = 'fe_event';

    // The settings "+" creates these. The content is ours, never a request's.
    const PAGES = [
        'events_page_id' => ['shortcode' => 'fluent_events', 'block' => 'fluent-event/events'],
        'event_page_id'  => ['shortcode' => 'fluent_event', 'block' => 'fluent-event/event'],
    ];

    // A setting's page while it is published, else 0: a trashed or draft page turns its links off.
    public static function pageId($key)
    {
        $id = (int) (Settings::get()[$key] ?? 0);

        return $id && get_post_type($id) === 'page' && get_post_status($id) === 'publish' ? $id : 0;
    }

    public static function listUrl()
    {
        $id = self::pageId('events_page_id');

        return $id ? (string) get_permalink($id) : '';
    }

    // Under the detail page by the event's post slug, which is unique among events.
    public static function url(Event $event)
    {
        $id = self::pageId('event_page_id');

        if (!$id) {
            return '';
        }

        $page = (string) get_permalink($id);

        // Plain permalinks have no path to append to, and the query var works without the rewrite rule.
        if (!get_option('permalink_structure')) {
            return add_query_arg(self::EVENT_VAR, $event->post->post_name, $page);
        }

        return user_trailingslashit(untrailingslashit($page) . '/' . $event->post->post_name);
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
     * @param array  $formatted EventService::formatEvent() shape, as a visitor sees it.
     * @param string $url       The event page.
     */
    // $justRegistered draws the confirmation the page opens right after a registration.
    public static function sideCard(Event $event, array $formatted, $url, $justRegistered = false)
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
            'just_registered'  => $justRegistered,
        ];

        if ($phase !== 'ended' && in_array($formatted['entry_type'], ['rsvp', 'open'], true)) {
            $data['registration'] = self::registration($event, $isSeated);
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

    // Everyone registers as a visitor and posts the event's own nonce, so only its page can register.
    // WordPress binds that nonce to a logged-in viewer's session, so their post also carries the REST
    // cookie nonce that lets it verify; nothing else of theirs is used.
    protected static function registration(Event $event, $isSeated)
    {
        $config = App::make('config');

        return [
            'is_going'    => $isSeated,
            'url'         => rest_url($config->get('app.rest_namespace') . '/' . $config->get('app.rest_version') . '/portal/events/' . (int) $event->id . '/register-guest'),
            'event_nonce' => wp_create_nonce('fe_portal_register_' . $event->id),
            'rest_nonce'  => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
        ];
    }
}
