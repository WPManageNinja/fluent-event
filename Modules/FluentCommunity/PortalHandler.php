<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentCommunity\App\Services\CustomSanitizer;
use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\App\Models\Event;
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

    protected $routeSlug;

    protected $routePaths;

    // fcom owns the slug (its FluentEvent settings); the filter can still move any single route.
    public function __construct($routeSlug = 'events')
    {
        $this->routeSlug = $routeSlug;
    }

    // Read on first use, not at construction (plugins_loaded): FluentSnippets and themes add filters at setup_theme.
    protected function routePaths()
    {
        if ($this->routePaths === null) {
            $this->routePaths = apply_filters('fluent_event/community/route_paths', [
                'events'           => $this->routeSlug,
                'create_event'     => $this->routeSlug . '/create',
                'edit_event'       => $this->routeSlug . '/:event_slug/edit',
                'event_view'       => $this->routeSlug . '/:event_slug',
                'space_events'     => $this->routeSlug,
                'space_event_view' => $this->routeSlug . '/:event_slug',
            ], $this->routeSlug);
        }

        return $this->routePaths;
    }

    public function register()
    {
        add_action('fluent_community/before_js_loaded', [$this, 'printVars']);
        add_filter('fluent_community/module_scripts', [$this, 'addModuleScript']);
        add_filter('fluent_community/portal_data_vars', [$this, 'addStylesheet'], 20);
        add_filter('fluent_community/default_main_menu_items', [$this, 'addMenuItem']);
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

    protected function menuItem()
    {
        return [
            'slug'         => self::MENU_SLUG,
            'title'        => __('Events', 'fluent-event'),
            'is_system'    => 'yes',
            'is_locked'    => 'no',
            'enabled'      => 'no',
            'permalink'    => FcomHelper::baseUrl($this->routePaths()['events']),
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

    // fcom serves the portal on a direct load only for listed first segments; the space routes sit under its `space`.
    public function addRoutePath($paths)
    {
        foreach (['events', 'create_event', 'edit_event', 'event_view'] as $key) {
            $paths[] = strtok($this->routePaths()[$key], '/');
        }

        return array_values(array_unique($paths));
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
        $isAdmin = current_user_can('manage_options');

        $vars = [
            'rest_url'            => esc_url_raw(rest_url('fluent-event/v2')),
            // Event covers go to the WordPress media library, as in the admin, not to fcom's uploads.
            'wp_rest_url'         => esc_url_raw(rest_url('wp/v2')),
            'is_logged_in'        => (bool) $userId,
            // Fills an open event's Register form for the member.
            'user'                => $user ? ['name' => $user->display_name, 'email' => $user->user_email] : null,
            'can_create_events'   => $isAdmin,
            // The way back to the FluentEvent admin, for the event menu's "View in FluentEvent".
            'admin_url'           => $isAdmin ? esc_url_raw(admin_url('admin.php?page=fluent-event')) : '',
            // fcom's own switch for posts outside any space; community-wide events need it on.
            'global_posts'        => FcomHelper::hasGlobalPost(),
            'default_view'        => $settings['default_events_view'],
            'disable_view_switch' => $settings['disable_event_view_switch'] === 'yes',
            'timezone'            => wp_timezone_string(),
            'start_of_week'       => (int) get_option('start_of_week', 0),
            'venue_max_length'    => Event::venueMaxLength(),
            'route_paths'         => $this->routePaths(),
            'i18n'                => $this->strings(),
            // The lazy chunks load their stylesheets themselves; this picks the .rtl.css twins.
            'is_rtl'              => Enqueue::isRtl(),
        ];

        wp_print_inline_script_tag('window.fluentEventFcomPortal = ' . wp_json_encode($vars, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';');
    }

    // Keyed by the English text, the same contract as the admin app's $t().
    protected function strings()
    {
        return [
            /* translators: 1: number of guests going, 2: the event's capacity */
            '%1$s out of %2$s going' => __('%1$s out of %2$s going', 'fluent-event'),
            /* translators: %d: the number of further events on that day */
            '%d more' => __('%d more', 'fluent-event'),
            /* translators: %d: seats still open on a capped event */
            '%d spots left' => __('%d spots left', 'fluent-event'),
            '1 spot left' => __('1 spot left', 'fluent-event'),
            /* translators: %s: number of guests going */
            '%s going' => __('%s going', 'fluent-event'),
            'A venue for in-person events, a link for online ones.' => __('A venue for in-person events, a link for online ones.', 'fluent-event'),
            'About This Event' => __('About This Event', 'fluent-event'),
            'Add a cover image' => __('Add a cover image', 'fluent-event'),
            'Add to calendar' => __('Add to calendar', 'fluent-event'),
            'Address' => __('Address', 'fluent-event'),
            'All' => __('All', 'fluent-event'),
            'All Events' => __('All Events', 'fluent-event'),
            'An online event needs a meeting link.' => __('An online event needs a meeting link.', 'fluent-event'),
            'Apple / Outlook' => __('Apple / Outlook', 'fluent-event'),
            'Back' => __('Back', 'fluent-event'),
            'Basics' => __('Basics', 'fluent-event'),
            'Calendar' => __('Calendar', 'fluent-event'),
            'Calendars' => __('Calendars', 'fluent-event'),
            'Capacity' => __('Capacity', 'fluent-event'),
            'Guest control' => __('Guest control', 'fluent-event'),
            'Seats and the guest count guests see.' => __('Seats and the guest count guests see.', 'fluent-event'),
            'Cards' => __('Cards', 'fluent-event'),
            'Check your inbox for the confirmation email, or register with a different email.' => __('Check your inbox for the confirmation email, or register with a different email.', 'fluent-event'),
            'Contact the host if you want to join again.' => __('Contact the host if you want to join again.', 'fluent-event'),
            'Choose a space for this event.' => __('Choose a space for this event.', 'fluent-event'),
            'Click to add a cover image' => __('Click to add a cover image', 'fluent-event'),
            'Comments' => __('Comments', 'fluent-event'),
            'Community-wide' => __('Community-wide', 'fluent-event'),
            'Cover Image' => __('Cover Image', 'fluent-event'),
            'Create Event' => __('Create Event', 'fluent-event'),
            'Dashboard' => __('Dashboard', 'fluent-event'),
            'Date and Time' => __('Date and Time', 'fluent-event'),
            'Day' => __('Day', 'fluent-event'),
            /* translators: %d: the day number within a multi-day event */
            'Day %d' => __('Day %d', 'fluent-event'),
            'Describe the event, or type / to add images, lists and more' => __('Describe the event, or type / to add images, lists and more', 'fluent-event'),
            'Description' => __('Description', 'fluent-event'),
            'Done' => __('Done', 'fluent-event'),
            'Edit Event' => __('Edit Event', 'fluent-event'),
            'Email' => __('Email', 'fluent-event'),
            'End' => __('End', 'fluent-event'),
            'End date' => __('End date', 'fluent-event'),
            'End must be after the start.' => __('End must be after the start.', 'fluent-event'),
            'End time' => __('End time', 'fluent-event'),
            'Ended' => __('Ended', 'fluent-event'),
            'Event full' => __('Event full', 'fluent-event'),
            'Entry Type' => __('Entry Type', 'fluent-event'),
            'Error' => __('Error', 'fluent-event'),
            'Event cover image' => __('Event cover image', 'fluent-event'),
            'Event Name' => __('Event Name', 'fluent-event'),
            'Event title is required.' => __('Event title is required.', 'fluent-event'),
            'Events' => __('Events', 'fluent-event'),
            'Events from across the community will show up here.' => __('Events from across the community will show up here.', 'fluent-event'),
            'Events posted in this space will show up here.' => __('Events posted in this space will show up here.', 'fluent-event'),
            'Events that have ended will show up here.' => __('Events that have ended will show up here.', 'fluent-event'),
            'Feed Post' => __('Feed Post', 'fluent-event'),
            'Global posts are turned off in FluentCommunity settings.' => __('Global posts are turned off in FluentCommunity settings.', 'fluent-event'),
            'Going' => __('Going', 'fluent-event'),
            'Google Calendar' => __('Google Calendar', 'fluent-event'),
            'Guests register with their name and email, no login needed.' => __('Guests register with their name and email, no login needed.', 'fluent-event'),
            'Happening now' => __('Happening now', 'fluent-event'),
            'How guests join and what they see.' => __('How guests join and what they see.', 'fluent-event'),
            'How many guests can be going at once. Leave empty for no limit.' => __('How many guests can be going at once. Leave empty for no limit.', 'fluent-event'),
            'In a space' => __('In a space', 'fluent-event'),
            'In Person' => __('In Person', 'fluent-event'),
            'Interested?' => __('Interested?', 'fluent-event'),
            'Join online' => __('Join online', 'fluent-event'),
            'Keep it' => __('Keep it', 'fluent-event'),
            'Load more' => __('Load more', 'fluent-event'),
            'Loading event' => __('Loading event', 'fluent-event'),
            'Loading events' => __('Loading events', 'fluent-event'),
            'Log in to RSVP' => __('Log in to RSVP', 'fluent-event'),
            'Logged-in members say whether they are going.' => __('Logged-in members say whether they are going.', 'fluent-event'),
            'Meeting Link' => __('Meeting Link', 'fluent-event'),
            'Members can RSVP once this post is published.' => __('Members can RSVP once this post is published.', 'fluent-event'),
            'Members can register once this post is published.' => __('Members can register once this post is published.', 'fluent-event'),
            'Month' => __('Month', 'fluent-event'),
            'More actions' => __('More actions', 'fluent-event'),
            'Name' => __('Name', 'fluent-event'),
            'Next' => __('Next', 'fluent-event'),
            'No events found' => __('No events found', 'fluent-event'),
            'No events on this day.' => __('No events on this day.', 'fluent-event'),
            'No events yet' => __('No events yet', 'fluent-event'),
            'No past events' => __('No past events', 'fluent-event'),
            'No upcoming events' => __('No upcoming events', 'fluent-event'),
            'Nobody registers. Anyone who can see the event sees all of it, meeting link included.' => __('Nobody registers. Anyone who can see the event sees all of it, meeting link included.', 'fluent-event'),
            'None' => __('None', 'fluent-event'),
            'Not going' => __('Not going', 'fluent-event'),
            'Not going anymore?' => __('Not going anymore?', 'fluent-event'),
            'OK' => __('OK', 'fluent-event'),
            'Online' => __('Online', 'fluent-event'),
            'Only http and https links are accepted.' => __('Only http and https links are accepted.', 'fluent-event'),
            'Only members of the space can see it.' => __('Only members of the space can see it.', 'fluent-event'),
            'Open' => __('Open', 'fluent-event'),
            'Optional. Shown on the event page.' => __('Optional. Shown on the event page.', 'fluent-event'),
            'Past' => __('Past', 'fluent-event'),
            'Please register for the event to see the meeting link.' => __('Please register for the event to see the meeting link.', 'fluent-event'),
            /* translators: 1: member's name, 2: space name (shown in bold) */
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
            'Select a space' => __('Select a space', 'fluent-event'),
            'Settings' => __('Settings', 'fluent-event'),
            'Show how many guests are going' => __('Show how many guests are going', 'fluent-event'),
            'Show how many spots are left' => __('Show how many spots are left', 'fluent-event'),
            'Shown at the top of the event page and on event cards.' => __('Shown at the top of the event page and on event cards.', 'fluent-event'),
            'Something went wrong. Please try again.' => __('Something went wrong. Please try again.', 'fluent-event'),
            'Space' => __('Space', 'fluent-event'),
            'Start' => __('Start', 'fluent-event'),
            'Start date' => __('Start date', 'fluent-event'),
            'Start time' => __('Start time', 'fluent-event'),
            /* translators: %s: when the event starts, as a date and time or as a relative time such as "in 3 days" */
            'Starts %s' => __('Starts %s', 'fluent-event'),
            'Street, city, country' => __('Street, city, country', 'fluent-event'),
            'Switch between card, timeline and calendar view' => __('Switch between card, timeline and calendar view', 'fluent-event'),
            'Switch between month, week and day view' => __('Switch between month, week and day view', 'fluent-event'),
            'There are no spaces you can post in.' => __('There are no spaces you can post in.', 'fluent-event'),
            'This email is already registered' => __('This email is already registered', 'fluent-event'),
            'This event is not available.' => __('This event is not available.', 'fluent-event'),
            'The event to clone was not found.' => __('The event to clone was not found.', 'fluent-event'),
            'Timeline' => __('Timeline', 'fluent-event'),
            'Timezone' => __('Timezone', 'fluent-event'),
            'To change the post text or its space, edit the post itself.' => __('To change the post text or its space, edit the post itself.', 'fluent-event'),
            /* translators: a menu path in FluentCommunity's admin; keep the names as FluentCommunity shows them */
            'To turn it on: FluentCommunity → Portal Settings → General → Global Post Status → Enable Global Posts Status Form.' => __('To turn it on: FluentCommunity → Portal Settings → General → Global Post Status → Enable Global Posts Status Form.', 'fluent-event'),
            'Today' => __('Today', 'fluent-event'),
            'This event is full and no longer taking registrations.' => __('This event is full and no longer taking registrations.', 'fluent-event'),
            'Try a different search term.' => __('Try a different search term.', 'fluent-event'),
            'Unlimited' => __('Unlimited', 'fluent-event'),
            'Upcoming' => __('Upcoming', 'fluent-event'),
            'Upcoming Events' => __('Upcoming Events', 'fluent-event'),
            'Update Event' => __('Update Event', 'fluent-event'),
            'Venue Name' => __('Venue Name', 'fluent-event'),
            'Venue Type' => __('Venue Type', 'fluent-event'),
            /* translators: %s: the event title */
            'View %s' => __('View %s', 'fluent-event'),
            'View all' => __('View all', 'fluent-event'),
            'View in FluentEvent' => __('View in FluentEvent', 'fluent-event'),
            'View post' => __('View post', 'fluent-event'),
            'Week' => __('Week', 'fluent-event'),
            'What members see in the feed, above the event.' => __('What members see in the feed, above the event.', 'fluent-event'),
            'What the event is called.' => __('What the event is called.', 'fluent-event'),
            'When the event starts and ends.' => __('When the event starts and ends.', 'fluent-event'),
            'Where do you want to post this event?' => __('Where do you want to post this event?', 'fluent-event'),
            /* translators: %s: event title */
            'You will lose your spot for %s. You can register again later.' => __('You will lose your spot for %s. You can register again later.', 'fluent-event'),
            'Your registration was cancelled' => __('Your registration was cancelled', 'fluent-event'),
            "You're registered" => __("You're registered", 'fluent-event'),
            'e.g. Friday Q&A' => __('e.g. Friday Q&A', 'fluent-event'),
            'e.g. Kingsway Hall' => __('e.g. Kingsway Hall', 'fluent-event'),
            // The event start's countdown and the event form's "Start Time Display" choice.
            'Start Time Display' => __('Start Time Display', 'fluent-event'),
            'How the event page shows the time until the start.' => __('How the event page shows the time until the start.', 'fluent-event'),
            'Time Left' => __('Time Left', 'fluent-event'),
            'Countdown' => __('Countdown', 'fluent-event'),
            'A short line, like "Starts in 3 days".' => __('A short line, like "Starts in 3 days".', 'fluent-event'),
            'Days, hours, minutes and seconds, ticking down to the start.' => __('Days, hours, minutes and seconds, ticking down to the start.', 'fluent-event'),
            'A short line with the time left, worked out when the page opens. It reads in the largest unit, like "Starts in 2 days" or "Starts in 5 hours".' => __('A short line with the time left, worked out when the page opens. It reads in the largest unit, like "Starts in 2 days" or "Starts in 5 hours".', 'fluent-event'),
            'Days, hours, minutes and seconds, ticking down every second while the page is open. Anything over a day stays in days, so six weeks reads 42 days.' => __('Days, hours, minutes and seconds, ticking down every second while the page is open. Anything over a day stays in days, so six weeks reads 42 days.', 'fluent-event'),
            'What the two choices look like' => __('What the two choices look like', 'fluent-event'),
            /* translators: %s is a choice name, "Time Left" or "Countdown"; the alt text of its example screenshot */
            '%s on the event page' => __('%s on the event page', 'fluent-event'),
            'Starts in 2 days' => __('Starts in 2 days', 'fluent-event'),
            'Days' => __('Days', 'fluent-event'),
            'Hours' => __('Hours', 'fluent-event'),
            'Minutes' => __('Minutes', 'fluent-event'),
            'Seconds' => __('Seconds', 'fluent-event'),
            'Time until the event starts' => __('Time until the event starts', 'fluent-event'),
        ];
    }
}
