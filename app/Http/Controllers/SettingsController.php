<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Models\Event;
use FluentEvent\App\Models\Guest;
use FluentEvent\App\Models\Post;
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
        $settings = Settings::get();
        $settings['portal_slug'] = Settings::portalSlug();

        return [
            'settings'        => $settings,
            'home_url'        => home_url('/'),
            'is_slug_defined' => defined('FLUENT_EVENT_PORTAL_SLUG'),
        ];
    }

    public function updateGeneral(Request $request)
    {
        $raw = $request->all();
        $data = SettingsRequest::sanitize($raw);

        // The constant owns the slug, and its value needn't pass the rules for the field it locks.
        if (defined('FLUENT_EVENT_PORTAL_SLUG')) {
            unset($data['portal_slug']);
        }

        // Blank means the site root, but text that sanitizes to nothing (`!!!`) stays raw so the rules reject it.
        if (isset($data['portal_slug']) && $data['portal_slug'] === '') {
            $data['portal_slug'] = trim((string) ($raw['portal_slug'] ?? ''));
        }

        $this->validate($data, SettingsRequest::generalRules(), SettingsRequest::messages());

        $previousSlug = Settings::portalSlug();
        $previousLanding = Settings::get()['enable_landing_pages'];

        $settings = Settings::update(Settings::GENERAL, $data);
        $settings['portal_slug'] = Settings::portalSlug();

        // `init` already registered the old rules, so flushing here would keep them. WordPress rebuilds on the next request.
        if ($settings['portal_slug'] !== $previousSlug || $settings['enable_landing_pages'] !== $previousLanding) {
            delete_option('rewrite_rules');
        }

        do_action('fluent_event/settings/general_updated', $settings);

        return [
            'message'  => __('Settings have been saved.', 'fluent-event'),
            'settings' => $settings,
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

        $this->validate($data, SettingsRequest::emailRules(), SettingsRequest::messages());

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
        $data = SettingsRequest::sanitize($request->all());

        $this->validate($data, SettingsRequest::emailNotificationRules(), SettingsRequest::messages());

        $settings = Settings::update(Settings::EMAIL_NOTIFICATIONS, $data);

        do_action('fluent_event/settings/email_notifications_updated', $settings);

        return [
            'message'  => __('Settings have been saved.', 'fluent-event'),
            'settings' => $settings,
        ];
    }

    public function previewEmail(Request $request, $type)
    {
        $this->validate(['type' => $type], ['type' => 'required|string|in:confirmation,cancellation,reminder']);

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
