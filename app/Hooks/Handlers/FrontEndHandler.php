<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\App\App;
use FluentEvent\App\CPT\EventPost;
use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Services\EventPageService;
use FluentEvent\App\Services\EventService;
use FluentEvent\App\Services\Settings;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\App\Utils\Enqueuer\Enqueue;
use FluentEvent\Framework\Support\Arr;

/**
 * The public pages, inside the theme. Each event opens at its own WordPress address, /<event_slug>/<post slug>/,
 * through the single-event template; [fluent_events] lists events and [fluent_event] shows one on any page.
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

    // The event its own address opened, found before the theme prints so a hidden one can answer 404.
    protected static $pageEvent;

    protected static $enqueued = false;

    public function register()
    {
        add_shortcode('fluent_events', [$this, 'renderEvents']);
        add_shortcode('fluent_event', [$this, 'renderEvent']);

        add_action('init', [$this, 'registerTemplates']);
        add_filter('template_include', [$this, 'classicTemplate']);
        add_action('template_redirect', [$this, 'resolveEventRequest']);
        add_action('pre_get_posts', [$this, 'limitListings']);
        add_filter('posts_results', [$this, 'hideFromLookups'], 10, 2);
        add_filter('oembed_request_post_id', [$this, 'hideFromEmbeds']);
        add_filter('redirect_canonical', [$this, 'hideFromRedirects']);
        add_filter('wp_robots', [$this, 'robots']);
        add_action('admin_bar_menu', [$this, 'addEditLink'], 80);
        add_action('wp_enqueue_scripts', [$this, 'maybeEnqueueAssets']);
        add_filter('document_title_parts', [$this, 'documentTitle']);
        add_filter('get_canonical_url', [$this, 'canonicalUrl'], 10, 2);
        add_action('wp_head', [$this, 'printMeta'], 1);
    }

    // Block themes: the theme's header and footer around ours, as FluentCart's product template does. The event's page
    // is built from one block per piece, so the admin can rearrange or drop them in the Site Editor; the template's
    // name is what WordPress's template hierarchy asks for.
    public function registerTemplates()
    {
        $spacer = '<!-- wp:spacer {"height":"var:preset|spacing|40"} -->'
            . '<div style="height:var(--wp--preset--spacing--40)" aria-hidden="true" class="wp-block-spacer"></div>'
            . '<!-- /wp:spacer -->';

        $event = '<!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"980px"}} --><div class="wp-block-group alignwide">'
            . $spacer
            . '<!-- wp:fluent-event/event-cover /-->'
            . '<!-- wp:columns --><div class="wp-block-columns">'
            . '<!-- wp:column {"width":"66.66%"} --><div class="wp-block-column" style="flex-basis:66.66%">'
            . '<!-- wp:fluent-event/event-title /--><!-- wp:fluent-event/event-meta /-->'
            . '<!-- wp:fluent-event/event-calendar /--><!-- wp:fluent-event/event-description /-->'
            . '</div><!-- /wp:column -->'
            . '<!-- wp:column {"width":"33.33%"} --><div class="wp-block-column" style="flex-basis:33.33%">'
            . '<!-- wp:fluent-event/event-registration /-->'
            . '</div><!-- /wp:column -->'
            . '</div><!-- /wp:columns -->'
            . $spacer
            . '</div><!-- /wp:group -->';

        $templates = [
            'single-' . EventPost::POST_TYPE  => [__('Single Event', 'fluent-event'), $event],
            'archive-' . EventPost::POST_TYPE => [__('Events Archive', 'fluent-event'), '<!-- wp:fluent-event/events {"align":"wide"} /-->'],
        ];

        foreach ($templates as $slug => list($title, $main)) {
            register_block_template('fluent-event//' . $slug, [
                'title'   => $title,
                'content' => '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
                    . '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} --><main class="wp-block-group">'
                    . $main
                    . '</main><!-- /wp:group -->'
                    . '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->',
            ]);
        }
    }

    // Classic themes: their header and footer around ours, unless the theme ships its own template for events.
    public function classicTemplate($template)
    {
        $ours = ['single-' . EventPost::POST_TYPE . '.php', 'archive-' . EventPost::POST_TYPE . '.php'];

        if (wp_is_block_theme() || in_array(basename($template), $ours, true)
            || !(is_singular(EventPost::POST_TYPE) || is_post_type_archive(EventPost::POST_TYPE))) {
            return $template;
        }

        return FLUENT_EVENT_PLUGIN_DIR . 'app/Views/portal/theme-page.php';
    }

    // WordPress found the post; our statuses decide whether the public may see it. The archive gives way to the All Events Page.
    public function resolveEventRequest()
    {
        if (is_post_type_archive(EventPost::POST_TYPE)) {
            $listPage = EventPageService::pageId('events_page_id');

            if ($listPage) {
                wp_safe_redirect(get_permalink($listPage), 301);
                exit;
            }

            return;
        }

        if (!is_singular(EventPost::POST_TYPE)) {
            return;
        }

        static::$pageEvent = $this->viewableEvents()->where('post_id', get_queried_object_id())->first();

        // A private calendar's event answers like a missing page, so a hidden one can't be told apart.
        if (!static::$pageEvent) {
            $GLOBALS['wp_query']->set_404();
            status_header(404);
            nocache_headers();
        }
    }

    // WordPress lists public posts in its archive, feeds and sitemaps; those list only what our events list shows.
    public function limitListings($query)
    {
        if (is_admin() || $query->is_singular() || !in_array(EventPost::POST_TYPE, (array) $query->get('post_type'), true)) {
            return;
        }

        $query->set('post__in', $this->eventPostIds(['public']) ?: [0]);
    }

    // WordPress also fetches a post by its ID (?p=) and then redirects to its address, which would hand out an
    // unlisted event's link: an event opens by its own link only, and a hidden one matches nothing at all.
    public function hideFromLookups($posts, $query)
    {
        if (is_admin() || !$query->is_singular()) {
            return $posts;
        }

        $ours = wp_list_pluck(wp_list_filter($posts, ['post_type' => EventPost::POST_TYPE]), 'ID');

        if (!$ours) {
            return $posts;
        }

        $allowed = $this->eventPostIds($query->get('name') !== '' ? ['public', 'unlisted'] : ['public'], $ours);

        return array_values(array_filter($posts, function ($post) use ($allowed) {
            return $post->post_type !== EventPost::POST_TYPE || in_array((int) $post->ID, $allowed, true);
        }));
    }

    // oEmbed finds the post from any URL, ?p= included, so only listed events embed.
    public function hideFromEmbeds($postId)
    {
        if (get_post_type($postId) !== EventPost::POST_TYPE) {
            return $postId;
        }

        return $this->eventPostIds(['public'], [$postId]) ? $postId : 0;
    }

    // WordPress redirects ?p= (or ?page_id=, ?attachment_id=) to the post's address even when its query found nothing.
    public function hideFromRedirects($redirectUrl)
    {
        $id = (int) max(get_query_var('p'), get_query_var('page_id'), get_query_var('attachment_id'));

        if ($id && get_post_type($id) === EventPost::POST_TYPE && !$this->eventPostIds(['public'], [$id])) {
            return false;
        }

        return $redirectUrl;
    }

    // Post IDs of events in these statuses whose calendar is public, optionally among the given posts.
    protected function eventPostIds(array $statuses, array $postIds = [])
    {
        $query = Event::query()
            ->byStatus($statuses)
            ->whereHas('calendar', function ($calendars) {
                $calendars->byStatus('public');
            });

        if ($postIds) {
            $query->whereIn('post_id', array_map('intval', $postIds));
        }

        return array_map('intval', $query->pluck('post_id')->all());
    }

    // An unlisted event opens by its link, but search engines shouldn't list it.
    public function robots($robots)
    {
        if (static::$pageEvent && static::$pageEvent->status === 'unlisted') {
            $robots['noindex'] = true;
        }

        return $robots;
    }

    // "Edit Event" in the toolbar opens the plugin's editor, as FluentCart's "Edit Product" does; WordPress has no editor for events.
    public function addEditLink($bar)
    {
        if (static::$pageEvent && current_user_can('manage_options')) {
            $bar->add_node([
                'id'    => 'edit',
                'title' => __('Edit Event', 'fluent-event'),
                'href'  => EventService::editUrl(static::$pageEvent->id),
            ]);
        }
    }

    public function maybeEnqueueAssets()
    {
        if (static::$pageEvent || is_post_type_archive(EventPost::POST_TYPE)) {
            $this->enqueueAssets();

            return;
        }

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

    // An untitled list page's tab reads what the shortcode shows.
    public function documentTitle($parts)
    {
        if ($this->isPage('events_page_id') && get_the_title() === '') {
            $calendar = $this->urlCalendar();
            $parts['title'] = $calendar ? $calendar->title : __('Events', 'fluent-event');
        }

        return $parts;
    }

    public function canonicalUrl($url, $post)
    {
        if ($this->isPage('events_page_id') && ($calendar = $this->urlCalendar())) {
            return add_query_arg('calendar', rawurlencode($calendar->slug), $url);
        }

        return $url;
    }

    public function printMeta()
    {
        if (static::$pageEvent) {
            $event = static::$pageEvent;
            $title = $event->title;
            $description = wp_trim_words(wp_strip_all_tags($event->description), 30)
                ?: DateTimeHelper::dateRange($event->start_at_site, $event->end_at_site);
            $url = EventPageService::url($event);
        } elseif ($this->isPage('events_page_id') || is_post_type_archive(EventPost::POST_TYPE)) {
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

    // [fluent_event event=""]: the event named, else the one whose own address is open (the single-event template).
    public function renderEvent($atts)
    {
        $atts = shortcode_atts(['event' => ''], $atts, 'fluent_event');
        $slug = sanitize_title($atts['event']);
        $event = $slug !== '' ? $this->findEvent($slug) : static::$pageEvent;

        $this->enqueueAssets();

        return $this->wrap($event ? $this->eventBody($event) : $this->notFoundBody());
    }

    // `alignwide`: a shortcode has no alignment setting, and a theme's content column (620px in TT4) squeezes the 980px frame.
    protected function wrap($body)
    {
        return '<div class="fe-portal alignwide"><div class="fe-portal__main">' . $body . '</div></div>';
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

    // A public or unlisted event in a public calendar: what an event's address opens.
    protected function viewableEvents()
    {
        return Event::query()
            ->with(['post', 'calendar'])
            ->byStatus(['public', 'unlisted'])
            ->whereHas('calendar', function ($query) {
                $query->byStatus('public');
            });
    }

    protected function findEvent($slug)
    {
        $slug = sanitize_title($slug);

        if ($slug === '') {
            return null;
        }

        return $this->viewableEvents()
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
        $isArchive = is_post_type_archive(EventPost::POST_TYPE);
        $url = $pageId ? (string) get_permalink($pageId) : ($isArchive ? (string) get_post_type_archive_link(EventPost::POST_TYPE) : home_url('/'));
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
            // Plain permalinks put the page (or the archive's post type) in the query, which a GET form would otherwise drop.
            'page_id'   => get_option('permalink_structure') ? '' : (string) $pageId,
            'post_type' => get_option('permalink_structure') || !$isArchive ? '' : EventPost::POST_TYPE,
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

    // One piece of the open event's page, for the single-event template's blocks; nothing where no event is open.
    public function renderEventPart($part)
    {
        static $data = [];

        $event = static::$pageEvent;

        if (!$event) {
            return '';
        }

        $this->enqueueAssets();
        // Six blocks ask for the same event: its seat count and formatting are worked out once.
        $data[spl_object_id($event)] = $data[spl_object_id($event)] ?? $this->eventData($event);

        if ($part === 'registration') {
            return $data[spl_object_id($event)]['side'];
        }

        return (string) App::make('view')->make('portal.event-part', $data[spl_object_id($event)] + ['part' => $part]);
    }

    // The community event page's shape, drawn for a visitor whoever is viewing: the public pages don't know their viewers.
    protected function eventBody(Event $event)
    {
        return (string) App::make('view')->make('portal.event', $this->eventData($event));
    }

    protected function eventData(Event $event)
    {
        $calendar = $event->calendar;
        $seats = EventService::seatCounts([$event->id]);
        $formatted = EventService::formatEvent($event, $seats[(int) $event->id] ?? 0, null, false, false);
        $isOnline = $event->venue_type === 'online';

        return [
            'event'          => $formatted,
            'description'    => $event->description,
            'calendar_title' => $calendar->title,
            'calendar_url'   => $this->calendarUrl($calendar->slug),
            'calendar_color' => $this->calendarColor($calendar),
            'is_online'      => $isOnline,
            'location'       => $isOnline ? __('Online', 'fluent-event') : ($formatted['venue'] ?: __('In person', 'fluent-event')),
            // The heading reads the site's clock, like every list; the side card leads with the event's own.
            'site_date'      => DateTimeHelper::dateRange($event->start_at_site, $event->end_at_site),
            'site_time'      => DateTimeHelper::timeRange($event->start_at_site, $event->end_at_site, wp_timezone_string()),
            'side'           => EventPageService::sideCard($event, $formatted, EventPageService::url($event)),
        ];
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
