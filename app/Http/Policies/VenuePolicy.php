<?php

namespace FluentEvent\App\Http\Policies;

use FluentEvent\App\Services\PermissionManager;
use FluentEvent\Framework\Http\Request\Request;

class VenuePolicy extends Policy
{
    public function verifyRequest(Request $request, ...$args)
    {
        return PermissionManager::userCan(['read_venues', 'write_venues']);
    }

    // The event form's picker, in the admin and the portal.
    public function getVenueOptions(Request $request, ...$args)
    {
        return PermissionManager::userCan(['read_venues', 'write_venues', 'read_events', 'write_events']);
    }

    public function createVenue(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_venues');
    }

    public function updateVenue(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_venues');
    }

    public function deleteVenue(Request $request, ...$args)
    {
        return PermissionManager::userCan('write_venues');
    }
}
