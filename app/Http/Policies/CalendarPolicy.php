<?php

namespace FluentEvent\App\Http\Policies;

use FluentEvent\App\Services\PermissionManager;
use FluentEvent\Framework\Http\Request\Request;

class CalendarPolicy extends Policy
{
    public function verifyRequest(Request $request, ...$args)
    {
        return PermissionManager::userCan(['read_calendars', 'write_calendars']);
    }

    public function createCalendar(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_calendars');
    }

    public function updateCalendar(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_calendars');
    }

    public function deleteCalendar(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_calendars');
    }
}
