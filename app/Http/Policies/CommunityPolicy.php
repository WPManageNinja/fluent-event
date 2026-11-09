<?php

namespace FluentEvent\App\Http\Policies;

use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\Framework\Http\Request\Request;

/**
 * Community members are not FluentEvent staff, so these routes are open to the
 * portal; each action checks the event's post visibility itself.
 */
class CommunityPolicy extends Policy
{
    public function verifyRequest(Request $request, ...$args)
    {
        return $this->portalAllows(get_current_user_id());
    }

    // Member registration (RSVP or open) is for logged-in members only; their REST cookie nonce is already checked.
    public function register(Request $request, ...$args)
    {
        $userId = get_current_user_id();

        return $userId > 0 && $this->portalAllows($userId);
    }

    // A logged-out visitor on a portal open to visitors; the handler checks the event's nonce.
    public function registerGuest(Request $request, ...$args)
    {
        return !get_current_user_id() && $this->portalAllows(0);
    }

    // Only the create-event page asks for the editor, and only site administrators reach it.
    public function getEditorAssets(Request $request, ...$args)
    {
        $userId = get_current_user_id();

        return $userId && user_can($userId, 'manage_options') && $this->portalAllows($userId);
    }

    // Called by the same create-event page, so the same site administrators.
    public function validateEvent(Request $request, ...$args)
    {
        return $this->getEditorAssets($request, ...$args);
    }

    private function portalAllows($userId)
    {
        if (!defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
            return false;
        }

        $profile = $userId ? XProfile::query()->where('user_id', $userId)->first() : null;

        if ($profile && $profile->status !== 'active') {
            return false;
        }

        return (bool) FcomHelper::canAccessPortal($userId);
    }
}
