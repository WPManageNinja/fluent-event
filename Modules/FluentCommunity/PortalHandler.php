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
            'rest_url'          => esc_url_raw(rest_url('fluent-event/v2')),
            'is_logged_in'      => (bool) $userId,
            'can_create_events' => $userId && PermissionManager::userCan('write_events', $userId),
            'default_view'      => $settings['default_events_view'],
            'i18n'              => $this->strings(),
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
            /* translators: %d: number of further events on that day */
            '%d more' => __('%d more', 'fluent-event'),
            /* translators: %s: number of guests going */
            '%s going' => __('%s going', 'fluent-event'),
            'Add Description' => __('Add Description', 'fluent-event'),
            'Add Venue Type' => __('Add Venue Type', 'fluent-event'),
            'Add a cover image' => __('Add a cover image', 'fluent-event'),
            'Address' => __('Address', 'fluent-event'),
            'All' => __('All', 'fluent-event'),
            'All Events' => __('All Events', 'fluent-event'),
            'Back' => __('Back', 'fluent-event'),
            'Calendar' => __('Calendar', 'fluent-event'),
            'Cards' => __('Cards', 'fluent-event'),
            'Choose a date and time in the future when you want your post to be published' => __('Choose a date and time in the future when you want your post to be published', 'fluent-event'),
            'Click to add a cover image' => __('Click to add a cover image', 'fluent-event'),
            'Create Event' => __('Create Event', 'fluent-event'),
            'Create new event' => __('Create new event', 'fluent-event'),
            'Date' => __('Date', 'fluent-event'),
            'Day' => __('Day', 'fluent-event'),
            /* translators: %d: day number within a multi-day event */
            'Day %d' => __('Day %d', 'fluent-event'),
            'Edit event' => __('Edit event', 'fluent-event'),
            'End' => __('End', 'fluent-event'),
            'End date' => __('End date', 'fluent-event'),
            'End time' => __('End time', 'fluent-event'),
            'Ended' => __('Ended', 'fluent-event'),
            'Entry type *' => __('Entry type *', 'fluent-event'),
            'Event Name' => __('Event Name', 'fluent-event'),
            'Event cover image' => __('Event cover image', 'fluent-event'),
            'Event name' => __('Event name', 'fluent-event'),
            'Events' => __('Events', 'fluent-event'),
            'Events from across the community will show up here.' => __('Events from across the community will show up here.', 'fluent-event'),
            'Events posted in this space will show up here.' => __('Events posted in this space will show up here.', 'fluent-event'),
            'Events that have ended will show up here.' => __('Events that have ended will show up here.', 'fluent-event'),
            'Feed post' => __('Feed post', 'fluent-event'),
            'Going' => __('Going', 'fluent-event'),
            'Guests' => __('Guests', 'fluent-event'),
            'How guests join, and how many can' => __('How guests join, and how many can', 'fluent-event'),
            'In person' => __('In person', 'fluent-event'),
            'Join the meeting' => __('Join the meeting', 'fluent-event'),
            'Load more' => __('Load more', 'fluent-event'),
            'Log in to RSVP' => __('Log in to RSVP', 'fluent-event'),
            'Logged-in members say whether they are going.' => __('Logged-in members say whether they are going.', 'fluent-event'),
            'Meeting link *' => __('Meeting link *', 'fluent-event'),
            'Month' => __('Month', 'fluent-event'),
            'More actions' => __('More actions', 'fluent-event'),
            'Next' => __('Next', 'fluent-event'),
            'No events found' => __('No events found', 'fluent-event'),
            'No events yet' => __('No events yet', 'fluent-event'),
            'No past events' => __('No past events', 'fluent-event'),
            'No upcoming events' => __('No upcoming events', 'fluent-event'),
            'Nobody registers. Anyone who can see the event sees all of it, meeting link included.' => __('Nobody registers. Anyone who can see the event sees all of it, meeting link included.', 'fluent-event'),
            'Not going' => __('Not going', 'fluent-event'),
            'Offline location or virtual link' => __('Offline location or virtual link', 'fluent-event'),
            'Online' => __('Online', 'fluent-event'),
            'Online event' => __('Online event', 'fluent-event'),
            'Open' => __('Open', 'fluent-event'),
            'Past' => __('Past', 'fluent-event'),
            'Please fill in the required fields.' => __('Please fill in the required fields.', 'fluent-event'),
            'Please register for the event to see the meeting link.' => __('Please register for the event to see the meeting link.', 'fluent-event'),
            'Post message *' => __('Post message *', 'fluent-event'),
            'Post title' => __('Post title', 'fluent-event'),
            'Post title *' => __('Post title *', 'fluent-event'),
            'Previous' => __('Previous', 'fluent-event'),
            'RSVP' => __('RSVP', 'fluent-event'),
            'Registration Type' => __('Registration Type', 'fluent-event'),
            'Remove Schedule' => __('Remove Schedule', 'fluent-event'),
            'Remove cover image' => __('Remove cover image', 'fluent-event'),
            'Schedule Post' => __('Schedule Post', 'fluent-event'),
            'Schedule post' => __('Schedule post', 'fluent-event'),
            /* translators: 1: scheduled date, 2: scheduled time */
            'Scheduled for %1$s at %2$s' => __('Scheduled for %1$s at %2$s', 'fluent-event'),
            'Search events' => __('Search events', 'fluent-event'),
            'See less' => __('See less', 'fluent-event'),
            'See more' => __('See more', 'fluent-event'),
            'Select Date' => __('Select Date', 'fluent-event'),
            'Select Schedule' => __('Select Schedule', 'fluent-event'),
            'Select Time' => __('Select Time', 'fluent-event'),
            'Show how many guests are going' => __('Show how many guests are going', 'fluent-event'),
            'Something went wrong. Please try again.' => __('Something went wrong. Please try again.', 'fluent-event'),
            'Start' => __('Start', 'fluent-event'),
            'Start date' => __('Start date', 'fluent-event'),
            'Start time' => __('Start time', 'fluent-event'),
            'Street, city, country' => __('Street, city, country', 'fluent-event'),
            'Switch between card, timeline and calendar view' => __('Switch between card, timeline and calendar view', 'fluent-event'),
            'Switch between month, week and day view' => __('Switch between month, week and day view', 'fluent-event'),
            'The date and time is based on your site\'s Timezone' => __('The date and time is based on your site\'s Timezone', 'fluent-event'),
            'This event is not available.' => __('This event is not available.', 'fluent-event'),
            'Time' => __('Time', 'fluent-event'),
            'Timeline' => __('Timeline', 'fluent-event'),
            'Timezone' => __('Timezone', 'fluent-event'),
            'Today' => __('Today', 'fluent-event'),
            'Try a different search term.' => __('Try a different search term.', 'fluent-event'),
            'Upcoming' => __('Upcoming', 'fluent-event'),
            'Venue name' => __('Venue name', 'fluent-event'),
            'Venue type' => __('Venue type', 'fluent-event'),
            /* translators: %s: event title */
            'View %s' => __('View %s', 'fluent-event'),
            'Week' => __('Week', 'fluent-event'),
            'What members see in the space feed, above the event.' => __('What members see in the space feed, above the event.', 'fluent-event'),
            'What should guests know before they come?' => __('What should guests know before they come?', 'fluent-event'),
            'e.g. Kingsway Hall' => __('e.g. Kingsway Hall', 'fluent-event'),
        ];
    }
}
