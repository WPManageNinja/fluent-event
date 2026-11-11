<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Models\Event;
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

        return [
            'calendars' => $calendars->map(function ($calendar) {
                return ['slug' => $calendar->slug, 'title' => $calendar->title];
            })->values()->all(),
            'events'    => $events->map(function ($event) {
                return [
                    'slug'  => $event->post->post_name,
                    'title' => $event->title,
                    'date'  => wp_date(get_option('date_format'), strtotime($event->start_at . ' UTC')),
                ];
            })->values()->all(),
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
