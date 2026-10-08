<?php

namespace FluentEvent\App\Http\Policies;

use FluentEvent\Framework\Http\Request\Request;

/**
 * Community members are not FluentEvent staff, so these routes are open to the
 * portal; each action checks the event's post visibility itself.
 */
class CommunityPolicy extends Policy
{
    public function verifyRequest(Request $request, ...$args)
    {
        return defined('FLUENT_COMMUNITY_PLUGIN_VERSION');
    }

    // RSVP is for logged-in members only; their REST cookie nonce is already checked.
    public function register(Request $request, ...$args)
    {
        return defined('FLUENT_COMMUNITY_PLUGIN_VERSION') && get_current_user_id() > 0;
    }
}
