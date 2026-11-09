<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentCommunity\App\Services\CustomSanitizer;
use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\App\Services\Icon;
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
        add_action('fluent_community/before_js_loaded', [$this, 'printVars']);
        add_filter('fluent_community/module_scripts', [$this, 'addModuleScript']);
        add_filter('fluent_community/portal_data_vars', [$this, 'addStylesheet'], 20);
        add_filter('fluent_community/default_main_menu_items', [$this, 'addMenuItem']);
        add_filter('fluent_community/menu_settings_api_response', [$this, 'addMenuSetting']);
        add_filter('fluent_community/mobile_menu', [$this, 'addMobileMenuItem']);
        add_filter('fluent_community/app_route_paths', [$this, 'addRoutePath']);
        add_filter('fluent_community/space_header_links', [$this, 'addSpaceTab'], 10, 2);
    }

    // Supplied beside fcom's built-ins on every request: a saved row keeps the admin's label, icon and order,
    // fcom takes the link from here, and with the plugin off nothing supplies it, so the row is dropped.
    public function addMenuItem($items)
    {
        $items[self::MENU_SLUG] = $this->menuItem();

        return $items;
    }

    // Listed in fcom's Menu Settings, off until an admin turns it on; fcom then stores it with its own items.
    public function addMenuSetting($data)
    {
        $items = (array) Arr::get($data, 'menuSettings.mainMenuItems', []);

        if (!in_array(self::MENU_SLUG, array_column($items, 'slug'), true)) {
            $items[] = $this->menuItem();

            $data['menuSettings']['mainMenuItems'] = $items;
        }

        return $data;
    }

    protected function menuItem()
    {
        return [
            'slug'         => self::MENU_SLUG,
            'title'        => __('Events', 'fluent-event'),
            'is_system'    => 'yes',
            'is_locked'    => 'no',
            'enabled'      => 'no',
            'permalink'    => FcomHelper::baseUrl('events'),
            'link_classes' => 'fcom_fe_events route_url',
            'shape_svg'    => Icon::CALENDAR,
        ];
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
        $paths[] = 'event';
        $paths[] = 'create-new-event';

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

    // fcom places it after its start.js and before its app, so the routes are in place before the router is built.
    public function addModuleScript($scripts)
    {
        $script = Enqueue::url('fcom-portal/fcom-portal.js');

        if ($script) {
            $scripts['fluent_event_fcom_portal'] = ['url' => $script];
        }

        return $scripts;
    }

    public function addStylesheet($vars)
    {
        $style = Enqueue::url('fcom-portal/fcom-portal.scss');

        if ($style) {
            $vars['css_files']['fluent_event_fcom_portal'] = ['url' => $style];
        }

        return $vars;
    }

    public function printVars()
    {
        $userId = get_current_user_id();
        $settings = Settings::get();
        $user = $userId ? wp_get_current_user() : null;

        $vars = [
            'rest_url'            => esc_url_raw(rest_url('fluent-event/v2')),
            'is_logged_in'        => (bool) $userId,
            // Fills an open event's Register form for the member.
            'user'                => $user ? ['name' => $user->display_name, 'email' => $user->user_email] : null,
            'can_create_events'   => current_user_can('manage_options'),
            // fcom's own switch for posts outside any space; community-wide events need it on.
            'global_posts'        => FcomHelper::hasGlobalPost(),
            'default_view'        => $settings['default_events_view'],
            'disable_view_switch' => $settings['disable_event_view_switch'] === 'yes',
            'timezone'            => wp_timezone_string(),
            'start_of_week'       => (int) get_option('start_of_week', 0),
            'i18n'                => $this->strings(),
        ];

        wp_print_inline_script_tag('window.fluentEventFcomPortal = ' . wp_json_encode($vars, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';');
    }

    // Keyed by the English text, the same contract as the admin app's $t().
    protected function strings()
    {
        return [
            /* translators: %d: number of further events on that day */
            '%d more' => __('%d more', 'fluent-event'),
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
            'An online event needs a meeting link.' => __('An online event needs a meeting link.', 'fluent-event'),
            'Apple / Outlook' => __('Apple / Outlook', 'fluent-event'),
            'Back' => __('Back', 'fluent-event'),
            'Basics' => __('Basics', 'fluent-event'),
            'Calendar' => __('Calendar', 'fluent-event'),
            'Cards' => __('Cards', 'fluent-event'),
            'Click to add a cover image' => __('Click to add a cover image', 'fluent-event'),
            'Community-wide' => __('Community-wide', 'fluent-event'),
            'Cover image' => __('Cover image', 'fluent-event'),
            'Create Event' => __('Create Event', 'fluent-event'),
            'Create new event' => __('Create new event', 'fluent-event'),
            'Date and time' => __('Date and time', 'fluent-event'),
            'Day' => __('Day', 'fluent-event'),
            /* translators: %d: day number within a multi-day event */
            'Day %d' => __('Day %d', 'fluent-event'),
            'Description' => __('Description', 'fluent-event'),
            'Edit event' => __('Edit event', 'fluent-event'),
            'Email' => __('Email', 'fluent-event'),
            'End' => __('End', 'fluent-event'),
            'End date' => __('End date', 'fluent-event'),
            'End must be after the start.' => __('End must be after the start.', 'fluent-event'),
            'End time' => __('End time', 'fluent-event'),
            'Ended' => __('Ended', 'fluent-event'),
            'Entry type' => __('Entry type', 'fluent-event'),
            'Error' => __('Error', 'fluent-event'),
            'Event cover image' => __('Event cover image', 'fluent-event'),
            'Event name' => __('Event name', 'fluent-event'),
            'Event title is required.' => __('Event title is required.', 'fluent-event'),
            'Events' => __('Events', 'fluent-event'),
            'Events from across the community will show up here.' => __('Events from across the community will show up here.', 'fluent-event'),
            'Events posted in this space will show up here.' => __('Events posted in this space will show up here.', 'fluent-event'),
            'Events that have ended will show up here.' => __('Events that have ended will show up here.', 'fluent-event'),
            'Feed post' => __('Feed post', 'fluent-event'),
            'Global posts are turned off in FluentCommunity settings.' => __('Global posts are turned off in FluentCommunity settings.', 'fluent-event'),
            'Going' => __('Going', 'fluent-event'),
            'Google Calendar' => __('Google Calendar', 'fluent-event'),
            'Guests register with their name and email, no login needed.' => __('Guests register with their name and email, no login needed.', 'fluent-event'),
            'Happening now' => __('Happening now', 'fluent-event'),
            'How guests join and what they see.' => __('How guests join and what they see.', 'fluent-event'),
            'In a space' => __('In a space', 'fluent-event'),
            'In person' => __('In person', 'fluent-event'),
            'Interested?' => __('Interested?', 'fluent-event'),
            'Join online' => __('Join online', 'fluent-event'),
            'Load more' => __('Load more', 'fluent-event'),
            'Loading event' => __('Loading event', 'fluent-event'),
            'Log in to RSVP' => __('Log in to RSVP', 'fluent-event'),
            'Logged-in members say whether they are going.' => __('Logged-in members say whether they are going.', 'fluent-event'),
            'Meeting link' => __('Meeting link', 'fluent-event'),
            'Month' => __('Month', 'fluent-event'),
            'More actions' => __('More actions', 'fluent-event'),
            'Name' => __('Name', 'fluent-event'),
            'Next' => __('Next', 'fluent-event'),
            'No events found' => __('No events found', 'fluent-event'),
            'No events yet' => __('No events yet', 'fluent-event'),
            'No past events' => __('No past events', 'fluent-event'),
            'No upcoming events' => __('No upcoming events', 'fluent-event'),
            'Nobody registers. Anyone who can see the event sees all of it, meeting link included.' => __('Nobody registers. Anyone who can see the event sees all of it, meeting link included.', 'fluent-event'),
            'None' => __('None', 'fluent-event'),
            'Not going' => __('Not going', 'fluent-event'),
            'Online' => __('Online', 'fluent-event'),
            'Only http and https links are accepted.' => __('Only http and https links are accepted.', 'fluent-event'),
            'Only members of the space can see it.' => __('Only members of the space can see it.', 'fluent-event'),
            'Open' => __('Open', 'fluent-event'),
            'Optional. Shown on the event page.' => __('Optional. Shown on the event page.', 'fluent-event'),
            'Past' => __('Past', 'fluent-event'),
            'Please register for the event to see the meeting link.' => __('Please register for the event to see the meeting link.', 'fluent-event'),
            /* translators: 1: member's name, 2: space name */
            'Posted by %1$s in %2$s' => __('Posted by %1$s in %2$s', 'fluent-event'),
            /* translators: %s: member's name */
            'Posted by %s on their profile' => __('Posted by %s on their profile', 'fluent-event'),
            'Posted on your profile, outside any space. Everyone in the community can see it.' => __('Posted on your profile, outside any space. Everyone in the community can see it.', 'fluent-event'),
            'Previous' => __('Previous', 'fluent-event'),
            'RSVP' => __('RSVP', 'fluent-event'),
            'Register' => __('Register', 'fluent-event'),
            'Registered' => __('Registered', 'fluent-event'),
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
            'Street, city, country' => __('Street, city, country', 'fluent-event'),
            'Switch between card, timeline and calendar view' => __('Switch between card, timeline and calendar view', 'fluent-event'),
            'Switch between month, week and day view' => __('Switch between month, week and day view', 'fluent-event'),
            'This event is not available.' => __('This event is not available.', 'fluent-event'),
            'Timeline' => __('Timeline', 'fluent-event'),
            'Timezone' => __('Timezone', 'fluent-event'),
            /* translators: a menu path in FluentCommunity's admin; keep the names as FluentCommunity shows them */
            'To turn it on: FluentCommunity → Portal Settings → General → Global Post Status → Enable Global Posts Status Form.' => __('To turn it on: FluentCommunity → Portal Settings → General → Global Post Status → Enable Global Posts Status Form.', 'fluent-event'),
            'Today' => __('Today', 'fluent-event'),
            'Try a different search term.' => __('Try a different search term.', 'fluent-event'),
            'Upcoming' => __('Upcoming', 'fluent-event'),
            'Venue name' => __('Venue name', 'fluent-event'),
            'Venue type' => __('Venue type', 'fluent-event'),
            /* translators: %s: event title */
            'View %s' => __('View %s', 'fluent-event'),
            'View post' => __('View post', 'fluent-event'),
            'Week' => __('Week', 'fluent-event'),
            'What members see in the feed, above the event.' => __('What members see in the feed, above the event.', 'fluent-event'),
            'What the event is called.' => __('What the event is called.', 'fluent-event'),
            'When the event starts and ends.' => __('When the event starts and ends.', 'fluent-event'),
            'Where do you want to post this event?' => __('Where do you want to post this event?', 'fluent-event'),
            'e.g. Friday Q&A' => __('e.g. Friday Q&A', 'fluent-event'),
            'e.g. Kingsway Hall' => __('e.g. Kingsway Hall', 'fluent-event'),
        ];
    }
}
