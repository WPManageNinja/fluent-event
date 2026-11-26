<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Post;
use FluentEvent\App\Services\EventPageService;
use FluentEvent\App\Services\EventReminderService;
use FluentEvent\App\Services\Mailer;
use FluentEvent\App\Services\Settings;
use FluentEvent\App\Utils\DateTimeHelper;
use FluentEvent\App\Http\Requests\SettingsRequest;
use FluentEvent\Framework\Http\Request\Request;

class SettingsController extends Controller
{
    public function getGeneral(Request $request)
    {
        return [
            'settings' => Settings::get(),
            'pages'    => $this->selectedPages(),
        ];
    }

    public function updateGeneral(Request $request)
    {
        $data = SettingsRequest::sanitize($request->all());

        $this->validate($data, SettingsRequest::generalRules($data), SettingsRequest::messages());

        foreach (array_keys(EventPageService::PAGES) as $key) {
            $id = (int) ($data[$key] ?? 0);

            if ($id && (get_post_type($id) !== 'page' || get_post_status($id) !== 'publish')) {
                return $this->sendError([
                    'message' => __('Choose a published page.', 'fluent-event'),
                    'errors'  => [$key => ['page' => __('Choose a published page.', 'fluent-event')]],
                ], 422);
            }
        }

        $previousSlug = Settings::get()['event_slug'];
        $settings = Settings::update(Settings::GENERAL, $data);

        // `init` already registered the old slug's rules, so flushing here would keep them. WordPress rebuilds on the next request.
        if ($settings['event_slug'] !== $previousSlug) {
            delete_option('rewrite_rules');
        }

        do_action('fluent_event/settings/general_updated', $settings);

        return [
            'message'  => __('Settings have been saved.', 'fluent-event'),
            'settings' => $settings,
            'pages'    => $this->selectedPages(),
        ];
    }

    // The page select's remote search, as FluentAffiliate's.
    public function getPages(Request $request)
    {
        $key = $request->getSafe('key', 'sanitize_key', '');

        $this->validate(['key' => $key], [
            'key' => 'required|in:' . implode(',', array_keys(EventPageService::PAGES)),
        ]);

        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            's'              => $request->getSafe('search', 'sanitize_text_field', ''),
            'posts_per_page' => 50,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        return [
            'pages' => array_map(function ($post) use ($key) {
                return $this->pageOption($post, $key);
            }, $pages),
        ];
    }

    // The "+" beside a page select: a page of the admin's title holding its block. The request never sets the content.
    public function createPage(Request $request)
    {
        $data = [
            'key'   => $request->getSafe('key', 'sanitize_key', ''),
            'title' => $request->getSafe('title', 'sanitize_text_field', ''),
        ];

        $this->validate($data, [
            'key'   => 'required|in:' . implode(',', array_keys(EventPageService::PAGES)),
            'title' => 'required|string|max:255',
        ], SettingsRequest::messages());

        if (!EventPageService::createPage($data['key'], $data['title'])) {
            return $this->sendError(['message' => __('The page could not be created.', 'fluent-event')], 422);
        }

        $settings = Settings::get();

        do_action('fluent_event/settings/general_updated', $settings);

        return [
            'message'  => __('The page has been created.', 'fluent-event'),
            'settings' => $settings,
            'pages'    => $this->selectedPages(),
        ];
    }

    // Each setting's saved page.
    private function selectedPages()
    {
        $pages = [];

        foreach (array_keys(EventPageService::PAGES) as $key) {
            $post = get_post((int) Settings::get()[$key]);

            $pages[$key] = $post && $post->post_type === 'page' && $post->post_status !== 'trash'
                ? $this->pageOption($post, $key)
                : null;
        }

        return $pages;
    }

    // A page as the select shows it, with whether it holds the setting's shortcode or block yet.
    private function pageOption($post, $key)
    {
        $page = EventPageService::PAGES[$key];

        return [
            'id'            => (int) $post->ID,
            'title'         => $post->post_title !== '' ? $post->post_title : __('(no title)', 'fluent-event'),
            'link'          => (string) get_permalink($post),
            // The tag by name: has_shortcode() also wants it registered, which a REST request needn't have.
            'has_shortcode' => (bool) preg_match('/' . get_shortcode_regex([$page['shortcode']]) . '/', $post->post_content)
                || has_block($page['block'], $post),
        ];
    }

    public function getEmail(Request $request)
    {
        $settings = Settings::get(Settings::EMAIL);

        return [
            'settings' => $settings,
        ];
    }

    public function updateEmail(Request $request)
    {
        $data = SettingsRequest::sanitize($request->all());

        $this->validate($data, SettingsRequest::emailRules($data), SettingsRequest::messages());

        $settings = Settings::update(Settings::EMAIL, $data);

        do_action('fluent_event/settings/email_updated', $settings);

        return [
            'message'  => __('Settings have been saved.', 'fluent-event'),
            'settings' => $settings,
        ];
    }

    public function getEmailNotifications(Request $request)
    {
        return [
            'settings' => Settings::get(Settings::EMAIL_NOTIFICATIONS),
        ];
    }

    public function updateEmailNotifications(Request $request)
    {
        $data = $request->all();

        // Before sanitizing: absint() would turn a 1.5 or -5 reminder time into 1 or 5 and save it without a word.
        $this->validate($data, SettingsRequest::emailNotificationRules(), SettingsRequest::messages());

        $settings = Settings::update(Settings::EMAIL_NOTIFICATIONS, SettingsRequest::emailNotifications($data));

        do_action('fluent_event/settings/email_notifications_updated', $settings);

        return [
            'message'  => __('Settings have been saved.', 'fluent-event'),
            'settings' => $settings,
        ];
    }

    public function previewEmail(Request $request, $type)
    {
        $this->validate(['type' => $type], ['type' => 'required|string|in:confirmation,cancellation,waitlist,reminder']);

        $timezone = wp_timezone()->getName();

        $event = new Event([
            'start_at'   => DateTimeHelper::convertToUtc('+7 days 10:00', $timezone),
            'timezone'   => $timezone,
            'venue_type' => 'online',
            'meta'       => ['online_meeting_url' => 'https://example.com/meeting'],
        ]);

        $post = new Post();
        $post->post_title = __('Community Meetup', 'fluent-event');
        $event->setRelation('post', $post);

        $guest = new Guest();
        /* translators: sample guest name shown in the email preview */
        $guest->name = __('Jane Doe', 'fluent-event');

        $email = EventReminderService::buildEmail($type, $event, $guest);

        return [
            'subject' => $email['subject'],
            'html'    => Mailer::render($email['body']),
        ];
    }
}
