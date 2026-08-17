<?php

namespace FluentEvent\App\Http\Policies;

use FluentEvent\App\Services\PermissionManager;
use FluentEvent\Framework\Http\Request\Request;

class ReportPolicy extends Policy
{
    public function verifyRequest(Request $request, ...$args)
    {
        // The dashboard is the landing screen — any permission holder
        // may read it.
        return PermissionManager::hasAnyPermission();
    }
}
