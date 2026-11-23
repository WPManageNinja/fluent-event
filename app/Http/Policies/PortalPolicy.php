<?php

namespace FluentEvent\App\Http\Policies;

use FluentEvent\Framework\Http\Request\Request;

/**
 * Registration on the public event page, open to anyone who can see the page, logged in or not;
 * the handler checks the event's own nonce and that the event is one the public may see.
 */
class PortalPolicy extends Policy
{
    // A logged-in member's own answer; the framework has already checked their REST cookie nonce.
    public function register(Request $request, ...$args)
    {
        return is_user_logged_in();
    }

    public function registerGuest(Request $request, ...$args)
    {
        return true;
    }
}
