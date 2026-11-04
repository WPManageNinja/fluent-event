<?php

namespace FluentEvent\App\Http\Policies;

use FluentEvent\App\Services\Settings;
use FluentEvent\Framework\Http\Request\Request;

/**
 * Registration on the public event page, open to anyone who can see the page, logged in or not;
 * the handler checks the event's own nonce. With landing pages off there is no page, so it doesn't answer.
 */
class PortalPolicy extends Policy
{
    public function registerGuest(Request $request, ...$args)
    {
        return Settings::landingPagesEnabled();
    }
}
