<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentCommunity\App\Services\CustomSanitizer;
use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\App\Services\Icon;
use FluentEvent\App\Services\PermissionManager;
use FluentEvent\App\Services\Settings;
use FluentEvent\App\Utils\Enqueuer\Enqueue;
use FluentEvent\Framework\Support\Arr;

/**
 * Everything FluentEvent adds to the community portal UI: the Events nav, the
 * space Events tab and the script that registers the portal routes and the
 * event card.
 */
class PortalHandler
{
    const MENU_SLUG = 'fluent_events';

    public function register()
    {
        add_action('fluent_community/before_js_loaded', [$this, 'printAssets']);
        add_filter('fluent_community/menu_settings_api_response', [$this, 'addMenuSetting']);
        add_filter('fluent_community/main_menu_items', [$this, 'refreshMenuItem']);
        add_filter('fluent_community/mobile_menu', [$this, 'addMobileMenuItem']);
        add_filter('fluent_community/app_route_paths', [$this, 'addRoutePath']);
        add_filter('fluent_community/space_header_links', [$this, 'addSpaceTab'], 10, 2);
    }

    // Listed in fcom's Menu Settings, off until an admin turns it on; fcom then stores it with its own items.
    public function addMenuSetting($data)
    {
        $items = (array) Arr::get($data, 'menuSettings.mainMenuItems', []);

        if (!in_array(self::MENU_SLUG, array_column($items, 'slug'), true)) {
            $items[] = [
                'slug'         => self::MENU_SLUG,
                'title'        => __('Events', 'fluent-event'),
                'is_system'    => 'yes',
                'is_locked'    => 'no',
                'enabled'      => 'no',
                'permalink'    => FcomHelper::baseUrl('events'),
                'link_classes' => 'fcom_fe_events route_url',
                'shape_svg'    => Icon::CALENDAR,
            ];

            $data['menuSettings']['mainMenuItems'] = $items;
        }

        return $data;
    }

    // The stored row keeps the admin's label, icon and order; the link itself stays ours, as fcom does for its built-ins.
    public function refreshMenuItem($items)
    {
        if (isset($items[self::MENU_SLUG])) {
            $items[self::MENU_SLUG]['permalink'] = FcomHelper::baseUrl('events');
            $items[self::MENU_SLUG]['link_classes'] = 'fcom_fe_events route_url';
        }

        return $items;
    }

    public function addMobileMenuItem($items)
    {
        $menuItem = Arr::get(FcomHelper::getMenuItemsGroup('view'), 'mainMenuItems.' . self::MENU_SLUG);

        if (!$menuItem) {
            return $items;
        }

        // The last item is Profile (or Login); Events goes just before it, like Chat.
        $lastItem = array_pop($items);
        $iconSvg = Arr::get($menuItem, 'shape_svg');

        $items[] = [
            'route'    => ['name' => 'fe_events'],
            'title'    => Arr::get($menuItem, 'title', __('Events', 'fluent-event')),
            'icon_svg' => $iconSvg ? CustomSanitizer::sanitizeSvg($iconSvg) : Icon::CALENDAR,
        ];

        if ($lastItem) {
            $items[] = $lastItem;
        }

        return $items;
    }

    public function addRoutePath($paths)
    {
        $paths[] = 'events';

        return $paths;
    }

    // Runs for every space on every portal load, so it must not query.
    public function addSpaceTab($links, $space)
    {
        if (!CommunityEvents::isDiscussionSpace($space)) {
            return $links;
        }

        // Off until a space admin turns it on; a saved space menu then decides.
        $links[] = [
            'slug'    => self::MENU_SLUG,
            'title'   => __('Events', 'fluent-event'),
            'route'   => ['name' => 'fe_space_events', 'params' => ['space' => $space->slug]],
            'enabled' => 'no',
        ];

        return $links;
    }

    public function printAssets()
    {
        $script = Enqueue::url('fcom-portal/fcom-portal.js');

        if (!$script) {
            return;
        }

        $userId = get_current_user_id();
        $settings = Settings::get();

        $vars = [
            'rest_url'            => esc_url_raw(rest_url('fluent-event/v2')),
            'is_logged_in'        => (bool) $userId,
            'can_create_events'   => $userId && PermissionManager::userCan('write_events', $userId),
            'can_save_venues'     => $userId && PermissionManager::userCan('write_venues', $userId),
            'default_view'        => $settings['default_events_view'],
            'disable_view_switch' => $settings['disable_event_view_switch'] === 'yes',
            'timezone'            => wp_timezone_string(),
            'start_of_week'       => (int) get_option('start_of_week', 0),
            'i18n'                => $this->strings(),
        ];

        $style = Enqueue::url('fcom-portal/fcom-portal.scss');
        $version = rawurlencode(FLUENT_EVENT_PLUGIN_VERSION);

        if ($style) {
            echo '<link rel="stylesheet" id="fluent_event_fcom_portal_css" href="' . esc_url($style . '?ver=' . $version) . '" />' . "\n";
        }

        echo '<script>window.fluentEventFcomPortal = ' . wp_json_encode($vars, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>' . "\n";
        // Printed before fcom's own modules, so its routes are in place before the router is built.
        echo '<script type="module" src="' . esc_url($script . '?ver=' . $version) . '"></script>' . "\n";
    }

    // Keyed by the English text, the same contract as the admin app's $t().
    protected function strings()
    {
        return [
            /* translators: 1: number of guests going, 2: the event's capacity */
            '%1$s out of %2$s going' => __('%1$s out of %2$s going', 'fluent-event'),
            /* translators: %d: number of further events on that day */
            '%d more' => __('%d more', 'fluent-event'),
            /* translators: %d: seats still open on a capped event */
            '%d spots left' => __('%d spots left', 'fluent-event'),
            '1 spot left' => __('1 spot left', 'fluent-event'),
            /* translators: %s: number of guests going */
            '%s going' => __('%s going', 'fluent-event'),
            'A venue for in-person events, a link for online ones.' => __('A venue for in-person events, a link for online ones.', 'fluent-event'),
            'About This Event' => __('About This Event', 'fluent-event'),
            'Add a cover image' => __('Add a cover image', 'fluent-event'),
            'Add image' => __('Add image', 'fluent-event'),
            'Add to calendar' => __('Add to calendar', 'fluent-event'),
            'Address' => __('Address', 'fluent-event'),
            'All' => __('All', 'fluent-event'),
            'All Events' => __('All Events', 'fluent-event'),
            'Apple / Outlook' => __('Apple / Outlook', 'fluent-event'),
            'Back' => __('Back', 'fluent-event'),
            'Basics' => __('Basics', 'fluent-event'),
            'Calendar' => __('Calendar', 'fluent-event'),
            'Cancelled' => __('Cancelled', 'fluent-event'),
            'Capacity' => __('Capacity', 'fluent-event'),
            'Cards' => __('Cards', 'fluent-event'),
            'Click to add a cover image' => __('Click to add a cover image', 'fluent-event'),
            'Contact the host if you would still like to attend.' => __('Contact the host if you would still like to attend.', 'fluent-event'),
            'Cover image' => __('Cover image', 'fluent-event'),
            'Create Event' => __('Create Event', 'fluent-event'),
            'Create new event' => __('Create new event', 'fluent-event'),
            'Date and time' => __('Date and time', 'fluent-event'),
            'Day' => __('Day', 'fluent-event'),
            /* translators: %d: day number within a multi-day event */
            'Day %d' => __('Day %d', 'fluent-event'),
            'Description' => __('Description', 'fluent-event'),
            'Edit event' => __('Edit event', 'fluent-event'),
            'End' => __('End', 'fluent-event'),
            'End date' => __('End date', 'fluent-event'),
            'End time' => __('End time', 'fluent-event'),
            'Ended' => __('Ended', 'fluent-event'),
            'Event full' => __('Event full', 'fluent-event'),
            'Entry type' => __('Entry type', 'fluent-event'),
            'Error' => __('Error', 'fluent-event'),
            'Event cover image' => __('Event cover image', 'fluent-event'),
            'Event name' => __('Event name', 'fluent-event'),
            'Events' => __('Events', 'fluent-event'),
            'Events from across the community will show up here.' => __('Events from across the community will show up here.', 'fluent-event'),
            'Events posted in this space will show up here.' => __('Events posted in this space will show up here.', 'fluent-event'),
            'Events that have ended will show up here.' => __('Events that have ended will show up here.', 'fluent-event'),
            'Feed post' => __('Feed post', 'fluent-event'),
            'Going' => __('Going', 'fluent-event'),
            'Google Calendar' => __('Google Calendar', 'fluent-event'),
            'Happening now' => __('Happening now', 'fluent-event'),
            'How guests join and what they see.' => __('How guests join and what they see.', 'fluent-event'),
            'How many members can say they are going. Leave empty for no limit.' => __('How many members can say they are going. Leave empty for no limit.', 'fluent-event'),
            'If a seat opens up, you will be moved to going.' => __('If a seat opens up, you will be moved to going.', 'fluent-event'),
            'In person' => __('In person', 'fluent-event'),
            'Interested?' => __('Interested?', 'fluent-event'),
            'Join online' => __('Join online', 'fluent-event'),
            'Join the waitlist to be moved to going if a seat opens up.' => __('Join the waitlist to be moved to going if a seat opens up.', 'fluent-event'),
            'Join waitlist' => __('Join waitlist', 'fluent-event'),
            'Let members join a waitlist when the event is full' => __('Let members join a waitlist when the event is full', 'fluent-event'),
            'Saved venue' => __('Saved venue', 'fluent-event'),
            'New venue' => __('New venue', 'fluent-event'),
            'Venue' => __('Venue', 'fluent-event'),
            'Save as a venue for future events' => __('Save as a venue for future events', 'fluent-event'),
            'Street and number' => __('Street and number', 'fluent-event'),
            'City' => __('City', 'fluent-event'),
            'ZIP code' => __('ZIP code', 'fluent-event'),
            'Country' => __('Country', 'fluent-event'),
            'Map link' => __('Map link', 'fluent-event'),
            'A Google Maps or other map link for guests.' => __('A Google Maps or other map link for guests.', 'fluent-event'),
            'Choose a saved venue' => __('Choose a saved venue', 'fluent-event'),
            'Open map' => __('Open map', 'fluent-event'),
            'Registration window' => __('Registration window', 'fluent-event'),
            'Guest control' => __('Guest control', 'fluent-event'),
            'Seats, the waitlist and the guest count members see.' => __('Seats, the waitlist and the guest count members see.', 'fluent-event'),
            'When members can start joining.' => __('When members can start joining.', 'fluent-event'),
            'If Opens is empty, registration opens as soon as the event is published. If Closes is empty, it stays open until the event ends.' => __('If Opens is empty, registration opens as soon as the event is published. If Closes is empty, it stays open until the event ends.', 'fluent-event'),
            /* translators: label for when registration opens */
            'Opens' => __('Opens', 'fluent-event'),
            /* translators: label for when registration closes */
            'Closes' => __('Closes', 'fluent-event'),
            'Not set' => __('Not set', 'fluent-event'),
            'Time before the event starts' => __('Time before the event starts', 'fluent-event'),
            /* translators: %s is the date and time registration opens */
            'Opens %s' => __('Opens %s', 'fluent-event'),
            'Minutes before' => __('Minutes before', 'fluent-event'),
            'Hours before' => __('Hours before', 'fluent-event'),
            'Days before' => __('Days before', 'fluent-event'),
            'Registration closed' => __('Registration closed', 'fluent-event'),
            'Registration not open yet' => __('Registration not open yet', 'fluent-event'),
            /* translators: %s is the date and time registration opens */
            'Registration opens %s.' => __('Registration opens %s.', 'fluent-event'),
            /* translators: %s is the date and time registration closes */
            'Registration closes %s' => __('Registration closes %s', 'fluent-event'),
            'This event is no longer taking registrations.' => __('This event is no longer taking registrations.', 'fluent-event'),
            'Load more' => __('Load more', 'fluent-event'),
            'Log in to RSVP' => __('Log in to RSVP', 'fluent-event'),
            'Logged-in members say whether they are going.' => __('Logged-in members say whether they are going.', 'fluent-event'),
            'Meeting link' => __('Meeting link', 'fluent-event'),
            'Month' => __('Month', 'fluent-event'),
            'More actions' => __('More actions', 'fluent-event'),
            'Next' => __('Next', 'fluent-event'),
            'No events found' => __('No events found', 'fluent-event'),
            'No events yet' => __('No events yet', 'fluent-event'),
            'No past events' => __('No past events', 'fluent-event'),
            'No upcoming events' => __('No upcoming events', 'fluent-event'),
            'Nobody registers. Anyone who can see the event sees all of it, meeting link included.' => __('Nobody registers. Anyone who can see the event sees all of it, meeting link included.', 'fluent-event'),
            'Not going' => __('Not going', 'fluent-event'),
            'Online' => __('Online', 'fluent-event'),
            'Only http and https links are accepted.' => __('Only http and https links are accepted.', 'fluent-event'),
            'Open' => __('Open', 'fluent-event'),
            'Optional. Shown on the event page.' => __('Optional. Shown on the event page.', 'fluent-event'),
            'Past' => __('Past', 'fluent-event'),
            'Please register for the event to see the meeting link.' => __('Please register for the event to see the meeting link.', 'fluent-event'),
            'Previous' => __('Previous', 'fluent-event'),
            'RSVP' => __('RSVP', 'fluent-event'),
            'Registration' => __('Registration', 'fluent-event'),
            'Remove cover image' => __('Remove cover image', 'fluent-event'),
            'Search events' => __('Search events', 'fluent-event'),
            'Show how many guests are going' => __('Show how many guests are going', 'fluent-event'),
            'Shown at the top of the event page and on event cards.' => __('Shown at the top of the event page and on event cards.', 'fluent-event'),
            'Something went wrong. Please try again.' => __('Something went wrong. Please try again.', 'fluent-event'),
            'Start' => __('Start', 'fluent-event'),
            'Start date' => __('Start date', 'fluent-event'),
            'Start time' => __('Start time', 'fluent-event'),
            /* translators: %s: relative time, e.g. "in 3 days" */
            'Starts %s' => __('Starts %s', 'fluent-event'),
            'Switch between card, timeline and calendar view' => __('Switch between card, timeline and calendar view', 'fluent-event'),
            'Switch between month, week and day view' => __('Switch between month, week and day view', 'fluent-event'),
            'This event is full and no longer taking registrations.' => __('This event is full and no longer taking registrations.', 'fluent-event'),
            'This event is not available.' => __('This event is not available.', 'fluent-event'),
            'Timeline' => __('Timeline', 'fluent-event'),
            'Timezone' => __('Timezone', 'fluent-event'),
            'Today' => __('Today', 'fluent-event'),
            'Try a different search term.' => __('Try a different search term.', 'fluent-event'),
            'Unlimited' => __('Unlimited', 'fluent-event'),
            'Upcoming' => __('Upcoming', 'fluent-event'),
            'Venue name' => __('Venue name', 'fluent-event'),
            'Venue type' => __('Venue type', 'fluent-event'),
            /* translators: %s: event title */
            'View %s' => __('View %s', 'fluent-event'),
            'Waitlisted' => __('Waitlisted', 'fluent-event'),
            'Week' => __('Week', 'fluent-event'),
            'What members see in the space feed, above the event.' => __('What members see in the space feed, above the event.', 'fluent-event'),
            'What the event is called.' => __('What the event is called.', 'fluent-event'),
            'When the event starts and ends.' => __('When the event starts and ends.', 'fluent-event'),
            'You are on the waitlist' => __('You are on the waitlist', 'fluent-event'),
            'Your registration has been cancelled' => __('Your registration has been cancelled', 'fluent-event'),
            'e.g. Friday Q&A' => __('e.g. Friday Q&A', 'fluent-event'),
            'e.g. Kingsway Hall' => __('e.g. Kingsway Hall', 'fluent-event'),
        ];
    }
}
