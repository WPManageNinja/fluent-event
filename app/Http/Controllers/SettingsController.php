<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\Services\PermissionManager;
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
        $data = SettingsRequest::sanitize($request->all());

        // The constant owns the slug, and its value needn't pass the rules for the field it locks.
        if (defined('FLUENT_EVENT_PORTAL_SLUG')) {
            unset($data['portal_slug']);
        }

        $this->validate($data, SettingsRequest::generalRules(), SettingsRequest::messages());

        $previousSlug = Settings::portalSlug();
        $previousLanding = Settings::get()['enable_landing_pages'];

        $slugChanged = isset($data['portal_slug']) && $data['portal_slug'] !== $previousSlug;
        $landingToggled = isset($data['enable_landing_pages']) && $data['enable_landing_pages'] !== $previousLanding;

        if (($slugChanged || $landingToggled) && !current_user_can('manage_options')) {
            return $this->sendError([
                'message' => __('Only a site administrator can change the landing pages or the portal slug.', 'fluent-event'),
            ], 403);
        }

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

    public function getTeam(Request $request)
    {
        $members = PermissionManager::getTeamMembers();
        $permissionSets = PermissionManager::allPermissionSets();

        return [
            'members'         => $members,
            'permission_sets' => $permissionSets,
        ];
    }
    
    public function upsertTeamMember(Request $request)
    {
        $data = SettingsRequest::sanitize($request->all());

        $this->validate($data, SettingsRequest::teamRules(), SettingsRequest::messages());

        $user = get_user_by('ID', $data['user_id']);

        if (user_can($user->ID, 'manage_options')) {
            return $this->sendError([
                'message' => __('Administrators already have full access and cannot be added.', 'fluent-event'),
            ], 422);
        }

        if (!array_intersect((array) $user->roles, ['editor', 'author', 'contributor'])) {
            return $this->sendError([
                'message' => __('Only editors, authors and contributors can be team members.', 'fluent-event'),
            ], 422);
        }

        $invalid = array_diff(
            $data['permissions'],
            array_keys(PermissionManager::allPermissionSets())
        );

        if ($invalid) {
            return $this->sendError([
                'message' => __('One or more selected permissions are not valid.', 'fluent-event'),
            ], 422);
        }

        $granted = PermissionManager::attachPermissions($user->ID, $data['permissions']);

        do_action('fluent_event/team/member_updated', $user->ID, $granted);

        return [
            'message' => __('Team member permissions have been saved.', 'fluent-event'),
            'members' => PermissionManager::getTeamMembers(),
        ];
    }

    public function removeTeamMember(Request $request, $userId)
    {
        $userId = absint($userId);

        PermissionManager::detachPermissions($userId);

        do_action('fluent_event/team/member_removed', $userId);

        return [
            'message' => __('Team member has been removed.', 'fluent-event'),
            'members' => PermissionManager::getTeamMembers(),
        ];
    }
}
