<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\App\App;
use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Services\EventPageService;
use FluentEvent\App\Services\EventService;
use FluentEvent\App\Services\Settings;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\App\Utils\Enqueuer\Enqueue;
use FluentEvent\Framework\Support\Arr;

/**
 * The public pages, inside the theme: [fluent_events] lists events and [fluent_event] shows one, on any
 * WordPress page. The event details page also answers /<its path>/<event-slug>/ through one rewrite rule.
 */
class FrontEndHandler
{
    const VIEWS = ['cards', 'timeline', 'calendar'];
    const FILTERS = ['upcoming', 'all', 'past'];
    const SPANS = ['month', 'week', 'day'];
    // Not `page`: WordPress owns that query var and redirects a page request carrying it.
    const PAGE_VAR = 'fe_page';
    // The week and day views draw an hour this tall, as the admin calendar does.
    const HOUR_PX = 48;
    const PER_PAGE = 10;
    const ASSET_HANDLE = 'fluent-event-portal';

    // The details page's event, found before the theme prints so a missing one can answer 404.
    protected static $pageEvent;

    protected static $enqueued = false;

    public function register()
    {
        add_shortcode('fluent_events', [$this, 'renderEvents']);
        add_shortcode('fluent_event', [$this, 'renderEvent']);

        add_action('init', [$this, 'addRewriteRule']);
        add_filter('query_vars', [$this, 'addQueryVar']);
        add_action('template_redirect', [$this, 'resolvePageEvent']);
        add_action('wp_enqueue_scripts', [$this, 'maybeEnqueueAssets']);
        add_filter('document_title_parts', [$this, 'documentTitle']);
        add_filter('get_canonical_url', [$this, 'canonicalUrl'], 10, 2);
        add_action('wp_head', [$this, 'printMeta'], 1);
        add_action('post_updated', [$this, 'maybeFlushRules'], 10, 3);
    }

    // A rule only takes effect after a flush: activation, the setting and the page's own edits run one.
    public function addRewriteRule()
    {
        $id = EventPageService::pageId('event_page_id');
        $path = $id ? get_page_uri($id) : '';

        if (!$path) {
            return;
        }

        add_rewrite_rule(
            '^' . preg_quote($path, '#') . '/([^/]+)/?$',
            'index.php?page_id=' . $id . '&' . EventPageService::EVENT_VAR . '=$matches[1]',
            'top'
        );
    }

    public function addQueryVar($vars)
    {
        $vars[] = EventPageService::EVENT_VAR;

        return $vars;
    }

    public function maybeFlushRules($postId, $after, $before)
    {
        if ((int) $postId !== (int) Settings::get()['event_page_id']) {
            return;
        }

        if ($after->post_name !== $before->post_name || $after->post_parent !== $before->post_parent || $after->post_status !== $before->post_status) {
            // `init` already registered the old rule, so flushing here would keep it. WordPress rebuilds on the next request.
            delete_option('rewrite_rules');
        }
    }

    public function resolvePageEvent()
    {
        if (!$this->isPage('event_page_id')) {
            return;
        }

        static::$pageEvent = $this->findEvent((string) get_query_var(EventPageService::EVENT_VAR));

        // An unknown, private or draft event answers like a missing page, so a hidden one can't be told apart.
        if (!static::$pageEvent) {
            status_header(404);
            nocache_headers();
        }
    }

    public function maybeEnqueueAssets()
    {
        $post = is_singular() ? get_post() : null;

        if ($post && (has_shortcode($post->post_content, 'fluent_events') || has_shortcode($post->post_content, 'fluent_event')
            || has_block('fluent-event/events', $post) || has_block('fluent-event/event', $post))) {
            $this->enqueueAssets();
        }
    }

    // In the head where the page holds our shortcode; from the render otherwise (a widget, a builder), which prints in the footer.
    protected function enqueueAssets()
    {
        if (static::$enqueued) {
            return;
        }

        static::$enqueued = true;

        Enqueue::style(self::ASSET_HANDLE, 'portal/portal.scss', [], FLUENT_EVENT_PLUGIN_VERSION);
        Enqueue::script(self::ASSET_HANDLE, 'portal/portal.js', [], FLUENT_EVENT_PLUGIN_VERSION, true);
    }

    // Our pages are untitled, so the tab reads what the shortcode shows.
    public function documentTitle($parts)
    {
        if ($this->isPage('event_page_id')) {
            $parts['title'] = static::$pageEvent ? static::$pageEvent->title : __('Page not found', 'fluent-event');
        } elseif ($this->isPage('events_page_id') && get_the_title() === '') {
            $calendar = $this->urlCalendar();
            $parts['title'] = $calendar ? $calendar->title : __('Events', 'fluent-event');
        }

        return $parts;
    }

    public function canonicalUrl($url, $post)
    {
        if (static::$pageEvent && $this->isPage('event_page_id')) {
            return EventPageService::url(static::$pageEvent);
        }

        if ($this->isPage('events_page_id') && ($calendar = $this->urlCalendar())) {
            return add_query_arg('calendar', rawurlencode($calendar->slug), $url);
        }

        return $url;
    }

    public function printMeta()
    {
        if ($this->isPage('event_page_id') && static::$pageEvent) {
            $event = static::$pageEvent;
            $title = $event->title;
            $description = wp_trim_words(wp_strip_all_tags($event->description), 30)
                ?: DateTimeHelper::dateRange($event->start_at_site, $event->end_at_site);
            $url = EventPageService::url($event);
        } elseif ($this->isPage('events_page_id')) {
            $calendar = $this->urlCalendar();
            $title = $calendar ? $calendar->title : __('Events', 'fluent-event');
            /* translators: %s: calendar title */
            $description = $calendar ? sprintf(__('Events from %s.', 'fluent-event'), $calendar->title) : __('Events from our calendars.', 'fluent-event');
            $url = $calendar ? add_query_arg('calendar', rawurlencode($calendar->slug), EventPageService::listUrl()) : EventPageService::listUrl();
        } else {
            return;
        }

        printf('<meta name="description" content="%s">' . "\n", esc_attr($description));
        printf('<meta property="og:type" content="website">' . "\n");
        printf('<meta property="og:title" content="%s">' . "\n", esc_attr($title));
        printf('<meta property="og:description" content="%s">' . "\n", esc_attr($description));
        printf('<meta property="og:url" content="%s">' . "\n", esc_url($url));
        printf('<meta property="og:site_name" content="%s">' . "\n", esc_attr(get_bloginfo('name')));
    }

    /**
     * [fluent_events calendar="" view="" filter="" view_switch="" show_calendars="yes"]: every public calendar's
     * events with a chip per calendar to switch. `calendar` picks the first chip; with the chips hidden it fixes
     * the list. `view` and `filter` are where the list starts, and the visitor's links take over while their
     * control shows. Empty `view` / `view_switch` fall back to the settings.
     */
    public function renderEvents($atts)
    {
        $atts = shortcode_atts([
            'calendar'       => '',
            'view'           => '',
            'filter'         => '',
            'view_switch'    => '',
            'show_calendars' => 'yes',
        ], $atts, 'fluent_events');

        $this->enqueueAssets();

        return $this->wrap($this->eventsBody($atts));
    }

    // [fluent_event event=""]: the event named, else the one in the details page's URL.
    public function renderEvent($atts)
    {
        $atts = shortcode_atts(['event' => ''], $atts, 'fluent_event');
        $slug = sanitize_title($atts['event']);

        if ($slug !== '') {
            $event = $this->findEvent($slug);
        } elseif ($this->isPage('event_page_id')) {
            $event = static::$pageEvent;
        } else {
            $event = $this->findEvent((string) get_query_var(EventPageService::EVENT_VAR));
        }

        $this->enqueueAssets();

        return $this->wrap($event ? $this->eventBody($event) : $this->notFoundBody());
    }

    protected function wrap($body)
    {
        return '<div class="fe-portal"><div class="fe-portal__main">' . $body . '</div></div>';
    }

    protected function isPage($key)
    {
        $id = EventPageService::pageId($key);

        return $id && is_page($id);
    }

    // The list page's `?calendar=`, for its title, canonical and description.
    protected function urlCalendar()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page state from the link.
        $slug = sanitize_title(wp_unslash($_GET['calendar'] ?? ''));

        return $slug === '' ? null : Calendar::query()->byStatus('public')->where('slug', $slug)->first();
    }

    // A public or unlisted event in a public calendar: what the details page opens.
    protected function findEvent($slug)
    {
        $slug = sanitize_title($slug);

        if ($slug === '') {
            return null;
        }

        return Event::query()
            ->with(['post', 'calendar'])
            ->byStatus(['public', 'unlisted'])
            ->whereHas('calendar', function ($query) {
                $query->byStatus('public');
            })
            ->whereHas('post', function ($query) use ($slug) {
                $query->where('post_name', $slug);
            })
            ->first();
    }

    protected function notFoundBody()
    {
        return (string) App::make('view')->make('portal.not-found', [
            'description' => __('This calendar or event doesn’t exist, or it isn’t open to the public.', 'fluent-event'),
            'portal_url'  => EventPageService::listUrl() ?: home_url('/'),
        ]);
    }

    protected function eventsBody(array $atts)
    {
        $calendars = Calendar::query()->byStatus('public')->orderBy('title', 'ASC')->get();
        $settings = Settings::get();

        $showChips = sanitize_key($atts['show_calendars']) !== 'no';
        $preset = sanitize_title($atts['calendar']);
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only page state from the link.
        // A chip's link names its calendar, "All" included once a preset would otherwise come back; hidden chips leave the list fixed.
        $picked = $showChips && isset($_GET['calendar']) ? sanitize_title(wp_unslash($_GET['calendar'])) : $preset;
        $calendar = $picked === '' ? null : $calendars->firstWhere('slug', $picked);
        // A fixed calendar that isn't public lists nothing; with the chips showing, the list falls back to All.
        $calendarIds = $calendar ? [$calendar->id] : (!$showChips && $preset !== '' ? [0] : $calendars->pluck('id')->all());

        $viewSwitch = sanitize_key($atts['view_switch']);
        $viewLocked = in_array($viewSwitch, ['yes', 'no'], true) ? $viewSwitch === 'no' : $settings['disable_event_view_switch'] === 'yes';
        $defaultView = in_array(sanitize_key($atts['view']), self::VIEWS, true) ? sanitize_key($atts['view']) : $settings['default_events_view'];
        $defaultFilter = in_array(sanitize_key($atts['filter']), self::FILTERS, true) ? sanitize_key($atts['filter']) : 'upcoming';

        $view = $viewLocked ? '' : sanitize_key(wp_unslash($_GET['view'] ?? ''));
        $filter = sanitize_key(wp_unslash($_GET['filter'] ?? ''));
        $search = sanitize_text_field(wp_unslash($_GET['search'] ?? ''));
        $span = sanitize_key(wp_unslash($_GET['span'] ?? ''));
        $date = sanitize_text_field(wp_unslash($_GET['date'] ?? ''));
        $pageNumber = max(1, absint($_GET[self::PAGE_VAR] ?? 1));
        // phpcs:enable

        $view = in_array($view, self::VIEWS, true) ? $view : $defaultView;
        $filter = in_array($filter, self::FILTERS, true) ? $filter : $defaultFilter;
        $span = in_array($span, self::SPANS, true) ? $span : 'month';
        $date = DateTimeHelper::isDate($date) ? $date : '';
        $pageId = (int) get_queried_object_id();
        $url = $pageId ? (string) get_permalink($pageId) : home_url('/');
        // Picking "All" must outlive the preset, so its link keeps an empty `calendar`.
        $keepEmpty = $showChips && $preset !== '' ? ['calendar'] : [];
        // Carried into every link, so switching one control keeps the others, the calendar's place included.
        $state = array_filter([
            'calendar' => $showChips && $calendar ? $calendar->slug : '',
            'view'     => $viewLocked ? '' : $view,
            'filter'   => $filter,
            'search'   => $search,
            'span'     => $view === 'calendar' && $span !== 'month' ? $span : '',
            'date'     => $view === 'calendar' ? $date : '',
            // Plain permalinks put the page in the query, which a GET form would otherwise drop.
            'page_id'  => get_option('permalink_structure') ? '' : (string) $pageId,
        ]) + array_fill_keys($keepEmpty, '');

        $events = Event::query()
            ->with(['post' => function ($query) {
                $query->select(['ID', 'post_title', 'post_name']);
            }])
            ->whereIn('calendar_id', $calendarIds)
            ->byStatus('public')
            ->searchBy($search);

        $calendarsById = $calendars->keyBy('id');
        $chips = [];

        if ($showChips && $calendars->count() > 1) {
            $chips[] = ['title' => __('All', 'fluent-event'), 'slug' => '', 'color' => '', 'active' => !$calendar];

            foreach ($calendars as $chipCalendar) {
                $chips[] = [
                    'title'  => $chipCalendar->title,
                    'slug'   => $chipCalendar->slug,
                    'color'  => $this->calendarColor($chipCalendar),
                    'active' => $calendar && (int) $calendar->id === (int) $chipCalendar->id,
                ];
            }
        }

        $data = [
            'chips'         => $chips,
            // Kept on a single calendar's list too, so picking a chip doesn't reflow every card; a fixed calendar needs no label.
            'show_calendar' => $calendars->count() > 1 && ($showChips || !$calendar),
            'keep_empty'    => $keepEmpty,
            'url'           => $url,
            'view'          => $view,
            'view_locked'   => $viewLocked,
            'filter'        => $filter,
            'search'        => $search,
            'state'         => $state,
            'page_var'      => self::PAGE_VAR,
        ];

        if ($view === 'calendar') {
            $data['grid'] = $this->calendarGrid($span, $date ?: wp_date('Y-m-d'), $events, $calendarsById);
        } else {
            $paginator = $events
                ->byPeriod($filter === 'all' ? '' : $filter)
                ->paginate(self::PER_PAGE, ['*'], 'page', $pageNumber);

            $data['rows'] = array_map(function ($event) use ($calendarsById) {
                return $this->eventRow($event, $calendarsById->get($event->calendar_id));
            }, $paginator->items());
            $data['current_page'] = $paginator->currentPage();
            $data['last_page'] = $paginator->lastPage();
        }

        return $this->rememberChoices($viewLocked ? '' : $defaultView, $defaultFilter)
            // make() returns the shared View, not HTML: left uncast, it renders the layout inside itself.
            . (string) App::make('view')->make('portal.events', $data);
    }

    // The events list filtered to one calendar.
    protected function calendarUrl($slug)
    {
        $list = EventPageService::listUrl();

        return $list ? add_query_arg('calendar', rawurlencode($slug), $list) : '';
    }

    protected function calendarColor(Calendar $calendar)
    {
        return (string) sanitize_hex_color(Arr::get((array) $calendar->meta, 'theme_color', ''));
    }

    // The community event page's shape, drawn for a visitor whoever is viewing: the public pages don't know their viewers.
    protected function eventBody(Event $event)
    {
        $calendar = $event->calendar;
        $seats = EventService::seatCounts([$event->id]);
        $formatted = EventService::formatEvent($event, $seats[(int) $event->id] ?? 0, null, false, false);
        $isOnline = $event->venue_type === 'online';

        $data = [
            'event'          => $formatted,
            'description'    => $event->description,
            'calendar_title' => $calendar->title,
            'calendar_url'   => $this->calendarUrl($calendar->slug),
            'calendar_color' => $this->calendarColor($calendar),
            'events_url'     => EventPageService::listUrl() ?: home_url('/'),
            'is_online'      => $isOnline,
            'location'       => $isOnline ? __('Online', 'fluent-event') : ($formatted['venue'] ?: __('In person', 'fluent-event')),
            // The heading reads the site's clock, like every list; the side card leads with the event's own.
            'site_date'      => DateTimeHelper::dateRange($event->start_at_site, $event->end_at_site),
            'site_time'      => DateTimeHelper::timeRange($event->start_at_site, $event->end_at_site, wp_timezone_string()),
            'side'           => EventPageService::sideCard($event, $formatted, EventPageService::url($event)),
        ];

        return (string) App::make('view')->make('portal.event', $data);
    }

    // Dates and times in the site's timezone and formats, as in the admin lists.
    protected function eventRow(Event $event, Calendar $calendar)
    {
        $start = strtotime($event->start_at . ' UTC');
        $end = strtotime($event->end_at . ' UTC');
        $timeFormat = get_option('time_format');
        // An end at midnight stays on the start's day, as calendarGrid() draws it.
        $endFormat = wp_date('Y-m-d', $start) === wp_date('Y-m-d', max($start, $end - 1)) ? $timeFormat : get_option('date_format') . ' ' . $timeFormat;
        $isOnline = $event->venue_type === 'online';

        return [
            'title'      => $event->title,
            'date'       => wp_date(get_option('date_format'), $start),
            'weekday'    => wp_date('l', $start),
            'time'       => wp_date($timeFormat, $start),
            /* translators: 1: start time, 2: end time or end date and time */
            'time_range' => sprintf(_x('%1$s – %2$s', 'time range', 'fluent-event'), wp_date($timeFormat, $start), wp_date($endFormat, $end)),
            'is_online'  => $isOnline,
            'location'   => $isOnline ? __('Online', 'fluent-event') : (Arr::get($event->meta, 'venue') ?: __('In person', 'fluent-event')),
            'cover_url'  => Arr::get($event->meta, 'cover_url', ''),
            'url'        => EventPageService::url($event),
            'calendar'   => $calendar->title,
            'color'      => $this->calendarColor($calendar),
            'start'      => $start,
            'end'        => $end,
        ];
    }

    // The calendar view, laid out like the admin's: a month of whole weeks, or a week or a day of hours. An event
    // shows on every day it covers, in its calendar's colour; days are the site's days, as in every list.
    protected function calendarGrid($span, $date, $events, $calendarsById)
    {
        global $wp_locale;

        $zone = wp_timezone();
        $anchor = date_create_immutable($date, $zone);
        $startOfWeek = (int) get_option('start_of_week');
        $weekStart = function (\DateTimeImmutable $day) use ($startOfWeek) {
            return $day->modify('-' . (((int) $day->format('w') - $startOfWeek + 7) % 7) . ' days');
        };

        if ($span === 'month') {
            // Stepping from the 1st, so a 31st can't skip the shorter month after it.
            $anchor = $anchor->modify('first day of this month');
            $from = $weekStart($anchor);
            $to = $weekStart($anchor->modify('last day of this month'))->modify('+6 days');
            $label = wp_date('F Y', $anchor->getTimestamp());
        } elseif ($span === 'week') {
            $from = $weekStart($anchor);
            $to = $from->modify('+6 days');
            /* translators: 1: first day of the week, 2: last day of the week with its year */
            $label = sprintf(_x('%1$s – %2$s', 'date range', 'fluent-event'), wp_date('M j', $from->getTimestamp()), wp_date('M j, Y', $to->getTimestamp()));
        } else {
            $from = $to = $anchor;
            $label = wp_date(get_option('date_format'), $anchor->getTimestamp());
        }

        $byDay = [];
        $rows = $events->inDateRange($from->format('Y-m-d'), $to->format('Y-m-d'))->orderBy('start_at', 'ASC')->get();

        foreach ($rows as $event) {
            $row = $this->eventRow($event, $calendarsById->get($event->calendar_id));
            $firstDay = date_create_immutable(wp_date('Y-m-d', $row['start']), $zone);
            // An event ending at midnight ends the day before.
            $lastDay = date_create_immutable(wp_date('Y-m-d', max($row['start'], $row['end'] - 1)), $zone);

            for ($day = max($firstDay, $from); $day <= min($lastDay, $to); $day = $day->modify('+1 day')) {
                $byDay[$day->format('Y-m-d')][] = $row + ['day_index' => $firstDay->diff($day)->days + 1];
            }
        }

        $today = wp_date('Y-m-d');
        $days = [];

        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $entries = $byDay[$key] ?? [];

            if ($span !== 'month') {
                $entries = $this->placeOnDay($entries, $day);
            }

            $days[] = [
                'date'    => $key,
                'number'  => (int) $day->format('j'),
                'name'    => $wp_locale->get_weekday_abbrev($wp_locale->get_weekday((int) $day->format('w'))),
                // Titles the "+N more" popover.
                'label'   => wp_date('l, M j', $day->getTimestamp()),
                'today'   => $key === $today,
                'outside' => $span === 'month' && $day->format('n') !== $anchor->format('n'),
                'entries' => $entries,
            ];
        }

        $hourFormat = preg_match('/[GH]/', get_option('time_format')) ? 'H:00' : 'g A';
        $hours = [];

        for ($hour = 0; $hour < 24; $hour++) {
            // A fixed UTC day, so a DST change can't shift the labels.
            $hours[] = $hour ? wp_date($hourFormat, gmmktime($hour, 0, 0, 1, 1, 2000), new \DateTimeZone('UTC')) : '';
        }

        return [
            'span'  => $span,
            'label' => $label,
            'prev'  => $anchor->modify('-1 ' . $span)->format('Y-m-d'),
            'next'  => $anchor->modify('+1 ' . $span)->format('Y-m-d'),
            'days'  => $days,
            'hours' => $span === 'month' ? [] : $hours,
        ];
    }

    // Week and day views: each event as a block from its start to its end within the day, at least half an hour tall.
    // Events that overlap share the column side by side, each in the first lane free when it starts.
    protected function placeOnDay(array $entries, \DateTimeImmutable $day)
    {
        $dayStart = $day->getTimestamp();
        $dayEnd = $day->modify('+1 day')->getTimestamp();
        // Clock minutes, not minutes elapsed: a daylight-saving day still lines up with the 24 hour labels.
        $clock = function ($timestamp) {
            return (int) wp_date('G', $timestamp) * 60 + (int) wp_date('i', $timestamp);
        };

        foreach ($entries as &$entry) {
            $entry['from'] = $entry['start'] <= $dayStart ? 0 : $clock($entry['start']);
            $entry['until'] = $entry['end'] >= $dayEnd ? 1440 : min(1440, max($clock($entry['end']), $entry['from'] + 30));
            $entry['top'] = (int) round($entry['from'] / 60 * self::HOUR_PX);
            $entry['height'] = (int) round(($entry['until'] - $entry['from']) / 60 * self::HOUR_PX) - 2;
        }
        unset($entry);

        usort($entries, function ($a, $b) {
            return $a['from'] <=> $b['from'];
        });

        // A group runs until no event in it is still on; its lanes split the column's width.
        $group = [];
        $laneEnds = [];
        $groupEnd = 0;

        foreach ($entries as $index => &$entry) {
            if ($entry['from'] >= $groupEnd) {
                foreach ($group as $member) {
                    $entries[$member]['lanes'] = count($laneEnds);
                }
                $group = $laneEnds = [];
            }

            $lane = 0;
            while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $entry['from']) {
                $lane++;
            }

            $laneEnds[$lane] = $entry['until'];
            $entry['lane'] = $lane;
            $group[] = $index;
            $groupEnd = max($groupEnd, $entry['until']);
        }
        unset($entry);

        foreach ($group as $member) {
            $entries[$member]['lanes'] = count($laneEnds);
        }

        return $entries;
    }

    // Like the admin's events list, the last view and tab are kept per browser. A link that names them wins
    // and is remembered; a bare link is redirected to the remembered choice. Inline ahead of the list, since
    // the theme's head has printed by the time a shortcode renders.
    protected function rememberChoices($defaultView, $defaultFilter)
    {
        $choices = [
            'filter' => ['key' => 'fe_portal_events_filter', 'allowed' => self::FILTERS, 'fallback' => $defaultFilter],
        ];

        if ($defaultView) {
            $choices['view'] = ['key' => 'fe_portal_events_view', 'allowed' => self::VIEWS, 'fallback' => $defaultView];
        }

        ob_start();
        ?>
        <script>
            (function (choices) {
                var params = new URLSearchParams(location.search);
                var changed = false;

                Object.keys(choices).forEach(function (param) {
                    var choice = choices[param];
                    var saved = null;

                    try {
                        if (params.has(param)) {
                            localStorage.setItem(choice.key, params.get(param));
                            return;
                        }
                        saved = localStorage.getItem(choice.key);
                    } catch (e) {
                        return;
                    }

                    if (saved && saved !== choice.fallback && choice.allowed.indexOf(saved) !== -1) {
                        params.set(param, saved);
                        changed = true;
                    }
                });

                if (changed) {
                    location.replace(location.pathname + '?' + params.toString() + location.hash);
                }
            })(<?php echo wp_json_encode($choices); ?>);
        </script>
        <?php

        return (string) ob_get_clean();
    }
}
