<?php

namespace FluentEvent\App\Http\Policies;

use FluentEvent\App\Services\EventPageService;
use FluentEvent\Framework\Http\Request\Request;

/**
 * Registration on the public event page, open to anyone who can see the page, logged in or not;
 * the handler checks the event's own nonce. With no event details page there is no form, so it doesn't answer.
 */
class PortalPolicy extends Policy
{
    public function registerGuest(Request $request, ...$args)
    {
        return EventPageService::pageId('event_page_id') > 0;
    }
}
