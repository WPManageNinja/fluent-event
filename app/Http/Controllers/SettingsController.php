<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Services\Settings;
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
}
