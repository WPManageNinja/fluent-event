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
 * Public landing pages under the portal slug, served as standalone documents
 * outside the theme, like FluentBooking's landing pages.
 */
class LandingPageHandler
{
    const QUERY_VAR = 'fe_route';
    const CALENDAR_VAR = 'fe_calendar';
    const EVENT_VAR = 'fe_event_slug';
    const VIEWS = ['cards', 'timeline', 'calendar'];
    const FILTERS = ['upcoming', 'all', 'past'];
    const PER_PAGE = 10;

    public function register()
    {
        add_action('init', [$this, 'addRewriteRules']);
        add_filter('query_vars', [$this, 'addQueryVar']);
        add_action('template_redirect', [$this, 'renderPage'], 1);
    }

    // A rule only takes effect after a flush, which activation runs.
    public function addRewriteRules()
    {
        $slug = Settings::portalSlug();

        add_rewrite_rule('^' . $slug . '/?$', 'index.php?' . self::QUERY_VAR . '=calendars', 'top');
        add_rewrite_rule('^' . $slug . '/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=calendar&' . self::CALENDAR_VAR . '=$matches[1]', 'top');
        add_rewrite_rule('^' . $slug . '/([^/]+)/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=event&' . self::CALENDAR_VAR . '=$matches[1]&' . self::EVENT_VAR . '=$matches[2]', 'top');
    }

    public function addQueryVar($vars)
    {
        $vars[] = self::QUERY_VAR;
        $vars[] = self::CALENDAR_VAR;
        $vars[] = self::EVENT_VAR;

        return $vars;
    }

    public function renderPage()
    {
        switch (get_query_var(self::QUERY_VAR)) {
            case 'calendars':
                $page = $this->calendarsPage();
                break;
            case 'calendar':
                $page = $this->calendarPage(get_query_var(self::CALENDAR_VAR));
                break;
            case 'event':
                $page = $this->eventPage(get_query_var(self::CALENDAR_VAR), get_query_var(self::EVENT_VAR));
                break;
            default:
                return;
        }

        // An unknown, private or draft calendar or event falls through to the theme's 404 page.
        if (!$page) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();

            return;
        }

        status_header(200);

        App::make('view')->render('portal.layout', $page + [
            'site_name'  => get_bloginfo('name'),
            'site_icon'  => get_site_icon_url(64),
            'portal_url' => home_url(user_trailingslashit(Settings::portalSlug())),
            'style_url'  => Enqueue::url('portal/portal.scss'),
            'script_url' => '',
        ]);

        exit;
    }

    // A page returns its layout title, description, canonical url and rendered body.
    protected function calendarsPage()
    {
        $calendars = Calendar::query()
            ->byStatus('public')
            ->withCount(['events as upcoming_events_count' => function ($query) {
                $query->byStatus('public')->where('end_at', '>=', gmdate(DateTimeHelper::FORMAT));
            }])
            ->orderBy('title', 'ASC')
            ->get();

        $description = __('Browse our calendars and their upcoming events.', 'fluent-event');

        return [
            /* translators: %s: site name */
            'title'       => sprintf(__('Calendars – %s', 'fluent-event'), get_bloginfo('name')),
            'description' => $description,
            'url'         => home_url(user_trailingslashit(Settings::portalSlug())),
            // make() returns the shared View, not HTML: left uncast, it renders the layout inside itself.
            'body'        => (string) App::make('view')->make('portal.calendars', [
                'calendars'   => $calendars,
                'description' => $description,
            ]),
        ];
    }

    // Unlisted calendars stay off the list above but open here by their link.
    protected function calendarPage($slug)
    {
        $calendar = Calendar::query()
            ->where('slug', sanitize_title($slug))
            ->byStatus(['public', 'unlisted'])
            ->first();

        if (!$calendar) {
            return null;
        }

        $settings = Settings::get();
        $viewLocked = $settings['disable_event_view_switch'] === 'yes';

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only page state from the link.
        $view = $viewLocked ? '' : sanitize_key(wp_unslash($_GET['view'] ?? ''));
        $filter = sanitize_key(wp_unslash($_GET['filter'] ?? ''));
        $search = sanitize_text_field(wp_unslash($_GET['search'] ?? ''));
        $month = sanitize_text_field(wp_unslash($_GET['month'] ?? ''));
        $pageNumber = max(1, absint($_GET['page'] ?? 1));
        // phpcs:enable

        $view = in_array($view, self::VIEWS, true) ? $view : $settings['default_events_view'];
        $filter = in_array($filter, self::FILTERS, true) ? $filter : 'upcoming';
        $url = home_url(user_trailingslashit(Settings::portalSlug() . '/' . $calendar->slug));

        $events = Event::query()
            ->with(['post' => function ($query) {
                $query->select(['ID', 'post_title', 'post_name']);
            }])
            ->byCalendar($calendar->id)
            ->byStatus('public')
            ->searchBy($search);

        $data = [
            'calendar'       => $calendar,
            'color'          => sanitize_hex_color(Arr::get((array) $calendar->meta, 'theme_color', '')),
            'upcoming_count' => Event::query()
                ->byCalendar($calendar->id)
                ->byStatus('public')
                ->where('end_at', '>=', gmdate(DateTimeHelper::FORMAT))
                ->count(),
            'url'            => $url,
            'list_url'       => home_url(user_trailingslashit(Settings::portalSlug())),
            'view'           => $view,
            'view_locked'    => $viewLocked,
            'filter'         => $filter,
            'search'         => $search,
            // Carried into every link, so switching one control keeps the others.
            'state'          => array_filter([
                'view'   => $viewLocked ? '' : $view,
                'filter' => $filter,
                'search' => $search,
            ]),
        ];

        if ($view === 'calendar') {
            $start = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
                ? date_create_immutable($month . '-01', wp_timezone())
                : date_create_immutable('midnight first day of this month', wp_timezone());

            $data['month'] = $this->monthGrid(
                $start,
                $events->inDateRange($start->format('Y-m-01'), $start->format('Y-m-t'))->orderBy('start_at', 'ASC')->get(),
                $url
            );
        } else {
            $paginator = $events
                ->byPeriod($filter === 'all' ? '' : $filter)
                ->paginate(self::PER_PAGE, ['*'], 'page', $pageNumber);

            $data['rows'] = array_map(function ($event) use ($url) {
                return $this->eventRow($event, $url);
            }, $paginator->items());
            $data['current_page'] = $paginator->currentPage();
            $data['last_page'] = $paginator->lastPage();
        }

        $this->rememberChoices($viewLocked ? '' : $settings['default_events_view']);

        $description = wp_trim_words((string) $calendar->description, 30);

        return [
            /* translators: 1: calendar title, 2: site name */
            'title'       => sprintf(_x('%1$s – %2$s', 'page title', 'fluent-event'), $calendar->title, get_bloginfo('name')),
            'description' => $description ?: __('Browse our calendars and their upcoming events.', 'fluent-event'),
            'url'         => $url,
            'body'        => (string) App::make('view')->make('portal.calendar', $data),
        ];
    }

    // The community event page, server-rendered. Opens like the calendar page: a public or unlisted
    // event in a public or unlisted calendar.
    protected function eventPage($calendarSlug, $eventSlug)
    {
        $calendar = Calendar::query()
            ->where('slug', sanitize_title($calendarSlug))
            ->byStatus(['public', 'unlisted'])
            ->first();

        if (!$calendar) {
            return null;
        }

        $event = Event::query()
            ->with(['post'])
            ->byCalendar($calendar->id)
            ->byStatus(['public', 'unlisted'])
            ->whereHas('post', function ($query) use ($eventSlug) {
                $query->where('post_name', sanitize_title($eventSlug));
            })
            ->first();

        if (!$event) {
            return null;
        }

        // The community page's shape, so the meeting link and guest count follow the same rules on both.
        $formatted = EventService::formatEvents([$event])[$event->id];
        $calendarUrl = home_url(user_trailingslashit(Settings::portalSlug() . '/' . $calendar->slug));
        $event->setRelation('calendar', $calendar);
        $url = EventPageService::url($event);
        $isOnline = $event->venue_type === 'online';

        $data = [
            'event'          => $formatted,
            'description'    => $event->description,
            'calendar_title' => $calendar->title,
            'calendar_url'   => $calendarUrl,
            'is_online'      => $isOnline,
            'location'       => $isOnline ? __('Online', 'fluent-event') : ($formatted['venue'] ?: __('In person', 'fluent-event')),
            // The heading reads the site's clock, like every list; the side card leads with the event's own.
            'site_date'      => DateTimeHelper::dateRange($event->start_at_site, $event->end_at_site),
            'site_time'      => DateTimeHelper::timeRange($event->start_at_site, $event->end_at_site, wp_timezone_string()),
            'side'           => EventPageService::sideCard($event, $formatted, $url),
        ];

        $description = wp_trim_words(wp_strip_all_tags($event->description), 30);

        return [
            /* translators: 1: event title, 2: site name */
            'title'       => sprintf(_x('%1$s – %2$s', 'page title', 'fluent-event'), $event->title, get_bloginfo('name')),
            'description' => $description ?: $data['site_date'],
            'url'         => $url,
            'body'        => (string) App::make('view')->make('portal.event', $data),
            'script_url'  => Enqueue::url('portal/portal.js'),
        ];
    }

    // Dates and times in the site's timezone and formats, as in the admin lists.
    protected function eventRow(Event $event, $calendarUrl)
    {
        $start = strtotime($event->start_at . ' UTC');
        $end = strtotime($event->end_at . ' UTC');
        $timeFormat = get_option('time_format');
        $endFormat = wp_date('Y-m-d', $start) === wp_date('Y-m-d', $end) ? $timeFormat : get_option('date_format') . ' ' . $timeFormat;
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
            'url'        => trailingslashit($calendarUrl) . user_trailingslashit($event->post->post_name),
            'start'      => $start,
        ];
    }

    protected function monthGrid(\DateTimeImmutable $start, $events, $calendarUrl)
    {
        global $wp_locale;

        $startOfWeek = (int) get_option('start_of_week');
        $firstDay = $start->getTimestamp();
        $byDay = [];

        foreach ($events as $event) {
            $row = $this->eventRow($event, $calendarUrl);
            // An event that began last month shows on the 1st.
            $byDay[(int) wp_date('j', max($row['start'], $firstDay))][] = $row;
        }

        // Titles the "+N more" popover; built in the site's timezone so the label names the right day.
        $dayLabels = [];
        foreach (array_keys($byDay) as $day) {
            $dayLabels[$day] = wp_date('l, M j', $start->setDate((int) $start->format('Y'), (int) $start->format('n'), $day)->getTimestamp());
        }

        $weekdays = [];
        for ($i = 0; $i < 7; $i++) {
            $weekdays[] = $wp_locale->get_weekday_abbrev($wp_locale->get_weekday(($startOfWeek + $i) % 7));
        }

        return [
            'label'    => wp_date('F Y', $firstDay),
            'value'    => $start->format('Y-m'),
            'prev'     => $start->modify('-1 month')->format('Y-m'),
            'next'     => $start->modify('+1 month')->format('Y-m'),
            'weekdays' => $weekdays,
            'lead'     => ((int) $start->format('w') - $startOfWeek + 7) % 7,
            'days'     => (int) $start->format('t'),
            'today'    => wp_date('Y-m') === $start->format('Y-m') ? (int) wp_date('j') : 0,
            'by_day'     => $byDay,
            'day_labels' => $dayLabels,
        ];
    }

    // Like the admin's events list, the last view and tab are kept per browser. A link that names them wins
    // and is remembered; a bare link is redirected to the remembered choice before the body paints.
    protected function rememberChoices($defaultView)
    {
        $choices = [
            'filter' => ['key' => 'fe_portal_events_filter', 'allowed' => self::FILTERS, 'fallback' => 'upcoming'],
        ];

        if ($defaultView) {
            $choices['view'] = ['key' => 'fe_portal_events_view', 'allowed' => self::VIEWS, 'fallback' => $defaultView];
        }

        add_action('fluent_event/portal/head', function () use ($choices) {
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
        });
    }
}
