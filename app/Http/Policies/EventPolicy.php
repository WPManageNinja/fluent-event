<?php

namespace FluentEvent\App\Http\Policies;

use FluentEvent\App\Services\PermissionManager;
use FluentEvent\Framework\Http\Request\Request;

class EventPolicy extends Policy
{
    public function verifyRequest(Request $request, ...$args)
    {
        return PermissionManager::userCan(['read_events', 'write_events']);
    }

    public function createEvent(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_events');
    }

    public function updateEvent(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_events');
    }

    public function deleteEvent(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_events');
    }

    public function getGuests(Request $request, ...$args)
    {
        return PermissionManager::userCan(['read_guests', 'write_guests']);
    }

    public function createGuest(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_guests');
    }

    public function updateGuest(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_guests');
    }

    public function deleteGuest(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_guests');
    }
}
