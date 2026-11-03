<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\App\App;
use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Services\Settings;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\App\Utils\Enqueuer\Enqueue;

/**
 * Public landing pages under the portal slug, served as standalone documents
 * outside the theme, like FluentBooking's landing pages.
 */
class LandingPageHandler
{
    const QUERY_VAR = 'fe_route';

    public function register()
    {
        add_action('init', [$this, 'addRewriteRules']);
        add_filter('query_vars', [$this, 'addQueryVar']);
        add_action('template_redirect', [$this, 'renderPage'], 1);
    }

    // A rule only takes effect after a flush, which activation runs.
    public function addRewriteRules()
    {
        add_rewrite_rule('^' . Settings::portalSlug() . '/?$', 'index.php?' . self::QUERY_VAR . '=calendars', 'top');
    }

    public function addQueryVar($vars)
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public function renderPage()
    {
        switch (get_query_var(self::QUERY_VAR)) {
            case 'calendars':
                $page = $this->calendarsPage();
                break;
            default:
                return;
        }

        status_header(200);

        App::make('view')->render('portal.layout', $page + [
            'site_name' => get_bloginfo('name'),
            'site_icon' => get_site_icon_url(64),
            'style_url' => Enqueue::url('portal/portal.scss'),
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
}
