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

    public function updateEmailNotifications(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_settings');
    }

    // Creating a WordPress page is a site-level change, beyond any team key.
    public function createPage(Request $request, ...$args)
    {
        return current_user_can('manage_options');
    }
}
