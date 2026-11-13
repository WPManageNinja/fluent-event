<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Services\Settings;
use FluentEvent\App\Utils\Enqueuer\Enqueue;
use FluentEvent\App\Utils\Enqueuer\Vite;
use FluentEvent\Framework\Support\Arr;

/**
 * The two blocks: [fluent_events] and [fluent_event] with their props in the sidebar. Server-rendered,
 * through the shortcodes' own render, as the Fluent suite's blocks are.
 */
class BlockEditorHandler
{
    const EDITOR_HANDLE = 'fluent-event-blocks';
    // The open event's page, one block per piece, for the single-event template.
    const EVENT_PARTS = ['cover', 'title', 'meta', 'calendar', 'description', 'registration'];
    const EDITOR_EVENTS_LIMIT = 200;

    public function register()
    {
        add_action('init', [$this, 'registerBlocks']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
        add_action('enqueue_block_assets', [$this, 'enqueueEditorStyles']);
    }

    public function registerBlocks()
    {
        $supports = ['align' => ['wide', 'full'], 'html' => false];

        register_block_type('fluent-event/events', [
            'render_callback' => [$this, 'renderEvents'],
            'supports'        => $supports,
            'attributes'      => [
                'calendar'       => ['type' => 'string', 'default' => ''],
                'view'           => ['type' => 'string', 'default' => ''],
                'filter'         => ['type' => 'string', 'default' => ''],
                'view_switch'    => ['type' => 'string', 'default' => ''],
                'show_calendars' => ['type' => 'string', 'default' => 'yes'],
            ],
        ]);

        register_block_type('fluent-event/event', [
            'render_callback' => [$this, 'renderEvent'],
            'supports'        => $supports,
            'attributes'      => [
                'event' => ['type' => 'string', 'default' => ''],
            ],
        ]);

        foreach (self::EVENT_PARTS as $part) {
            register_block_type('fluent-event/event-' . $part, [
                'render_callback' => function () use ($part) {
                    $html = (new FrontEndHandler())->renderEventPart($part);

                    return $html === '' ? '' : '<div ' . get_block_wrapper_attributes(['class' => 'fe-portal']) . '>' . $html . '</div>';
                },
                'supports'        => ['html' => false],
            ]);
        }
    }

    // Anyone who can edit a post saves attributes as they like, so each is held to the values the shortcode takes.
    public function renderEvents($attributes)
    {
        return $this->wrap((new FrontEndHandler())->renderEvents([
            'calendar'       => sanitize_title((string) Arr::get($attributes, 'calendar', '')),
            'view'           => $this->allowed($attributes, 'view', FrontEndHandler::VIEWS),
            'filter'         => $this->allowed($attributes, 'filter', FrontEndHandler::FILTERS),
            'view_switch'    => $this->allowed($attributes, 'view_switch', ['yes', 'no']),
            'show_calendars' => $this->allowed($attributes, 'show_calendars', ['yes', 'no']) ?: 'yes',
        ]));
    }

    public function renderEvent($attributes)
    {
        return $this->wrap((new FrontEndHandler())->renderEvent([
            'event' => sanitize_title((string) Arr::get($attributes, 'event', '')),
        ]));
    }

    public function enqueueEditorAssets()
    {
        Enqueue::script(
            self::EDITOR_HANDLE,
            'blocks/blocks.jsx',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-i18n'],
            // The dev server reads a JSX file's type from the URL's last dot, so `?ver=1.0.0` would make it `.0`.
            Vite::isOnDevMode() ? null : FLUENT_EVENT_PLUGIN_VERSION,
            true
        );

        // Localized, not an inline script: the module tag the enqueuer rewrites drops inline before/after code.
        wp_localize_script(self::EDITOR_HANDLE, 'fluentEventBlocks', $this->editorData());
    }

    // The placeholders' styles, on the hook that reaches the editor's iframe, with the event page's own so a
    // template piece's sample looks as it will on the page.
    public function enqueueEditorStyles()
    {
        if (is_admin()) {
            Enqueue::style(FrontEndHandler::ASSET_HANDLE, 'portal/portal.scss', [], FLUENT_EVENT_PLUGIN_VERSION);
            Enqueue::style(self::EDITOR_HANDLE, 'blocks/blocks.scss', [FrontEndHandler::ASSET_HANDLE], FLUENT_EVENT_PLUGIN_VERSION);
        }
    }

    // The sidebar's pickers: public calendars, and the events the details page opens, upcoming first.
    protected function editorData()
    {
        $calendars = Calendar::query()->byStatus('public')->orderBy('title', 'ASC')->get(['id', 'title', 'slug']);

        $events = Event::query()
            ->with(['post' => function ($query) {
                $query->select(['ID', 'post_title', 'post_name']);
            }])
            // An unlisted event opens only by its link, so only an admin, who sees it anyway, gets it in the picker.
            ->byStatus(current_user_can('manage_options') ? ['public', 'unlisted'] : ['public'])
            ->whereIn('calendar_id', $calendars->pluck('id')->all() ?: [0])
            ->byPeriod('')
            ->limit(self::EDITOR_EVENTS_LIMIT)
            ->get();

        $settings = Settings::get();

        return [
            // What an empty view or switcher setting falls back to, so the sample list starts where the page will.
            'view'        => $settings['default_events_view'],
            'view_locked' => $settings['disable_event_view_switch'] === 'yes',
            'week_start'  => (int) get_option('start_of_week'),
            'calendars'   => $calendars->map(function ($calendar) {
                return ['slug' => $calendar->slug, 'title' => $calendar->title];
            })->values()->all(),
            'events'      => $events->map(function ($event) {
                return [
                    'slug'  => $event->post->post_name,
                    'title' => $event->title,
                    'date'  => wp_date(get_option('date_format'), strtotime($event->start_at . ' UTC')),
                ];
            })->values()->all(),
            'i18n'        => $this->strings(),
        ];
    }

    // Keyed by the English text, the same contract as the admin app's $t(). The editor script reads it in
    // resources/blocks/i18n.js; a new __() string in the blocks needs its line here.
    protected function strings()
    {
        return [
            '10:00 AM – 12:00 PM' => __('10:00 AM – 12:00 PM', 'fluent-event'),
            '12 going' => __('12 going', 'fluent-event'),
            '3:00 PM – 4:00 PM' => __('3:00 PM – 4:00 PM', 'fluent-event'),
            '6:00 PM – 8:00 PM' => __('6:00 PM – 8:00 PM', 'fluent-event'),
            'A link to the open event’s calendar.' => __('A link to the open event’s calendar.', 'fluent-event'),
            'A list of your public events, as cards, a timeline or a calendar.' => __('A list of your public events, as cards, a timeline or a calendar.', 'fluent-event'),
            'About This Event' => __('About This Event', 'fluent-event'),
            'All' => __('All', 'fluent-event'),
            'All calendars' => __('All calendars', 'fluent-event'),
            'calendar' => __('calendar', 'fluent-event'),
            'Calendar' => __('Calendar', 'fluent-event'),
            'Cards' => __('Cards', 'fluent-event'),
            'City Library' => __('City Library', 'fluent-event'),
            'Community Hall' => __('Community Hall', 'fluent-event'),
            'Cover image' => __('Cover image', 'fluent-event'),
            'Day' => __('Day', 'fluent-event'),
            'event' => __('event', 'fluent-event'),
            'Event' => __('Event', 'fluent-event'),
            'Event Calendar' => __('Event Calendar', 'fluent-event'),
            'Event Cover' => __('Event Cover', 'fluent-event'),
            'Event date' => __('Event date', 'fluent-event'),
            'Event Description' => __('Event Description', 'fluent-event'),
            'Event Meta' => __('Event Meta', 'fluent-event'),
            'Event Registration' => __('Event Registration', 'fluent-event'),
            'Event title' => __('Event title', 'fluent-event'),
            'Event Title' => __('Event Title', 'fluent-event'),
            'events' => __('events', 'fluent-event'),
            'Events list' => __('Events list', 'fluent-event'),
            'Events shown first' => __('Events shown first', 'fluent-event'),
            'First calendar' => __('First calendar', 'fluent-event'),
            'Fluent Event' => __('Fluent Event', 'fluent-event'),
            'Fluent Events' => __('Fluent Events', 'fluent-event'),
            'Hands-on Design Workshop' => __('Hands-on Design Workshop', 'fluent-event'),
            'Hide' => __('Hide', 'fluent-event'),
            'Meetups' => __('Meetups', 'fluent-event'),
            'Month' => __('Month', 'fluent-event'),
            'Monthly Community Meetup' => __('Monthly Community Meetup', 'fluent-event'),
            'One event with its details and registration.' => __('One event with its details and registration.', 'fluent-event'),
            'Online' => __('Online', 'fluent-event'),
            'Past' => __('Past', 'fluent-event'),
            'Product Launch Livestream' => __('Product Launch Livestream', 'fluent-event'),
            'Register' => __('Register', 'fluent-event'),
            'registration' => __('registration', 'fluent-event'),
            'Sample events: the page shows your public events here.' => __('Sample events: the page shows your public events here.', 'fluent-event'),
            'Search events' => __('Search events', 'fluent-event'),
            'Select an event' => __('Select an event', 'fluent-event'),
            'Show' => __('Show', 'fluent-event'),
            'Show calendar pills' => __('Show calendar pills', 'fluent-event'),
            'Start – end time' => __('Start – end time', 'fluent-event'),
            'The calendar picked first. Visitors can switch with the calendar pills.' => __('The calendar picked first. Visitors can switch with the calendar pills.', 'fluent-event'),
            'The event’s description shows here.' => __('The event’s description shows here.', 'fluent-event'),
            'The open event’s cover image.' => __('The open event’s cover image.', 'fluent-event'),
            'The open event’s date, time and place.' => __('The open event’s date, time and place.', 'fluent-event'),
            'The open event’s description.' => __('The open event’s description.', 'fluent-event'),
            'The open event’s title.' => __('The open event’s title.', 'fluent-event'),
            'Timeline' => __('Timeline', 'fluent-event'),
            'Today' => __('Today', 'fluent-event'),
            'Upcoming' => __('Upcoming', 'fluent-event'),
            'Use the setting' => __('Use the setting', 'fluent-event'),
            'View' => __('View', 'fluent-event'),
            'View switcher' => __('View switcher', 'fluent-event'),
            'Webinars' => __('Webinars', 'fluent-event'),
            'Week' => __('Week', 'fluent-event'),
            'When and where, and the Register button.' => __('When and where, and the Register button.', 'fluent-event'),
            'With the pills hidden, the list stays on this calendar.' => __('With the pills hidden, the list stays on this calendar.', 'fluent-event'),
            'Workshops' => __('Workshops', 'fluent-event'),
        ];
    }

    protected function allowed($attributes, $key, array $values)
    {
        $value = Arr::get($attributes, $key, '');

        return is_string($value) && in_array($value, $values, true) ? $value : '';
    }

    // The block's own wrapper carries its alignment class.
    protected function wrap($html)
    {
        return '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
    }
}
