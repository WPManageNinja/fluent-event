<?php

namespace FluentEvent\App\Http\Policies;

use FluentEvent\Framework\Http\Request\Request;

/**
 * Registration on the public event page, open to anyone who can see the page, logged in or not;
 * the handler checks the event's own nonce and that the event is one the public may see.
 */
class PortalPolicy extends Policy
{
    public function registerGuest(Request $request, ...$args)
    {
        return true;
    }
}
