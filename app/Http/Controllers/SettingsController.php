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
        return $this->sendSuccess([
            'settings' => Settings::get(),
        ]);
    }

    public function updateGeneral(Request $request)
    {
        $data = SettingsRequest::sanitize($request->all());

        $this->validate($data, SettingsRequest::generalRules());

        $settings = Settings::update(Settings::GENERAL, $data);

        do_action('fluent_event/settings/general_updated', $settings);

        return $this->sendSuccess([
            'message'  => __('Settings have been saved.', 'fluent-event'),
            'settings' => $settings,
        ]);
    }


    public function getTeam(Request $request)
    {
        $members = PermissionManager::getTeamMembers();
        $permissionSets = PermissionManager::allPermissionSets();

        return $this->sendSuccess([
            'members'         => $members,
            'permission_sets' => $permissionSets,
        ]);
    }
    
    public function upsertTeamMember(Request $request)
    {
        $data = SettingsRequest::sanitize($request->all());

        $this->validate($data, SettingsRequest::teamRules(), SettingsRequest::teamMessages());

        $user = get_user_by('ID', $data['user_id']);

        if (user_can($user->ID, 'manage_options')) {
            return $this->sendError([
                'message' => __('Administrators already have full access and cannot be added.', 'fluent-event'),
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

        return $this->sendSuccess([
            'message' => __('Team member permissions have been saved.', 'fluent-event'),
            'members' => PermissionManager::getTeamMembers(),
        ]);
    }

    public function removeTeamMember(Request $request, $userId)
    {
        $userId = absint($userId);

        PermissionManager::detachPermissions($userId);

        do_action('fluent_event/team/member_removed', $userId);

        return $this->sendSuccess([
            'message' => __('Team member has been removed.', 'fluent-event'),
            'members' => PermissionManager::getTeamMembers(),
        ]);
    }
}
