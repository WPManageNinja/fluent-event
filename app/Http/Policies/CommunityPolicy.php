<?php

namespace FluentEvent\App\Http\Policies;

use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\App\Services\PermissionManager;
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

    // RSVP is for logged-in members only; their REST cookie nonce is already checked.
    public function register(Request $request, ...$args)
    {
        $userId = get_current_user_id();

        return $userId > 0 && $this->portalAllows($userId);
    }

    // Only the create-event page asks for the editor, and only event creators reach it.
    public function getEditorAssets(Request $request, ...$args)
    {
        $userId = get_current_user_id();

        return $userId && PermissionManager::userCan('write_events', $userId) && $this->portalAllows($userId);
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
