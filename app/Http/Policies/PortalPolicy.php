<?php

namespace FluentEvent\App\Http\Policies;

use FluentEvent\Framework\Http\Request\Request;

/**
 * Registration on the public event page, open to anyone who can see the page; each action checks
 * the event itself.
 */
class PortalPolicy extends Policy
{
    // A logged-in member; the framework has already checked their REST cookie nonce.
    public function register(Request $request, ...$args)
    {
        return get_current_user_id() > 0;
    }

    // A logged-out visitor; the handler checks the event's own nonce.
    public function registerGuest(Request $request, ...$args)
    {
        return !get_current_user_id();
    }
}
