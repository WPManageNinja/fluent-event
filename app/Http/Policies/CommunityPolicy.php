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

    public function register(Request $request, ...$args)
    {
        if (!defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
            return false;
        }

        // A logged-in request already passed WordPress's REST cookie nonce.
        if (get_current_user_id()) {
            return true;
        }

        return (bool) wp_verify_nonce((string) $request->get('_nonce'), 'fluent_event_register');
    }

    public function cancel(Request $request, ...$args)
    {
        return defined('FLUENT_COMMUNITY_PLUGIN_VERSION') && get_current_user_id() > 0;
    }
}
