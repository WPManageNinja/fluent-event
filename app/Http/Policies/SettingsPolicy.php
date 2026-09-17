<?php

namespace FluentEvent\App\Http\Policies;

use FluentEvent\App\Services\PermissionManager;
use FluentEvent\Framework\Http\Request\Request;

class SettingsPolicy extends Policy
{
    public function verifyRequest(Request $request, ...$args)
    {
        return PermissionManager::userCan(['read_settings', 'write_settings']);
    }

    public function updateGeneral(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_settings');
    }

    public function updateEmail(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_settings');
    }

    public function getTeam(Request $request, ...$args)
    {
        return PermissionManager::userCan(PermissionManager::ALL_DATA);
    }

    public function upsertTeamMember(Request $request, ...$args)
    {
        return PermissionManager::userCan(PermissionManager::ALL_DATA);
    }

    public function removeTeamMember(Request $request, ...$args)
    {
        return PermissionManager::userCan(PermissionManager::ALL_DATA);
    }
}
