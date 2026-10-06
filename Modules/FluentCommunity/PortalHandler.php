<?php

namespace FluentEvent\Modules\FluentCommunity;

use FluentCommunity\App\Services\Helper as FcomHelper;
use FluentEvent\App\Services\Icon;
use FluentEvent\App\Services\PermissionManager;
use FluentEvent\App\Services\Settings;
use FluentEvent\App\Utils\Enqueuer\Enqueue;

/**
 * Everything FluentEvent adds to the community portal UI: the Events nav, the
 * space Events tab and the script that registers the portal routes and the
 * event card.
 */
class PortalHandler
{
    public function register()
    {
        add_action('fluent_community/before_js_loaded', [$this, 'printAssets']);
        add_filter('fluent_community/main_menu_items', [$this, 'addMenuItem']);
        add_filter('fluent_community/mobile_menu', [$this, 'addMobileMenuItem']);
        add_filter('fluent_community/app_route_paths', [$this, 'addRoutePath']);
        add_filter('fluent_community/space_header_links', [$this, 'addSpaceTab'], 10, 2);
    }

    public function addMenuItem($items)
    {
        $items['fluent_events'] = [
            'slug'         => 'fluent_events',
            'title'        => __('Events', 'fluent-event'),
            'enabled'      => 'yes',
            'permalink'    => FcomHelper::baseUrl('events'),
            'link_classes' => 'fcom_fe_events route_url',
            'shape_svg'    => Icon::CALENDAR,
        ];

        return $items;
    }

    public function addMobileMenuItem($items)
    {
        // The last item is Profile (or Login); Events goes just before it, like Chat.
        $lastItem = array_pop($items);

        $items[] = [
            'route'    => ['name' => 'fe_events'],
            'title'    => __('Events', 'fluent-event'),
            'icon_svg' => Icon::CALENDAR,
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

        $links[] = [
            'slug'  => 'fluent_events',
            'title' => __('Events', 'fluent-event'),
            'route' => ['name' => 'fe_space_events', 'params' => ['space' => $space->slug]],
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
            'register_nonce'    => wp_create_nonce('fluent_event_register'),
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
            /* translators: 1: guests going, 2: seats available */
            '%1$s / %2$s going' => __('%1$s / %2$s going', 'fluent-event'),
            /* translators: %s: number of guests going */
            '%s going' => __('%s going', 'fluent-event'),
            'Address' => __('Address', 'fluent-event'),
            'All Events' => __('All Events', 'fluent-event'),
            'Back' => __('Back', 'fluent-event'),
            'Calendar' => __('Calendar', 'fluent-event'),
            'Cancel' => __('Cancel', 'fluent-event'),
            'Cancel registration' => __('Cancel registration', 'fluent-event'),
            'Cover image' => __('Cover image', 'fluent-event'),
            'Create new event' => __('Create new event', 'fluent-event'),
            'Edit event' => __('Edit event', 'fluent-event'),
            'Email' => __('Email', 'fluent-event'),
            'Ends' => __('Ends', 'fluent-event'),
            'Event' => __('Event', 'fluent-event'),
            'Event description' => __('Event description', 'fluent-event'),
            'Event is full' => __('Event is full', 'fluent-event'),
            'Event title' => __('Event title', 'fluent-event'),
            'Events' => __('Events', 'fluent-event'),
            'Full' => __('Full', 'fluent-event'),
            'Going' => __('Going', 'fluent-event'),
            'In person' => __('In person', 'fluent-event'),
            'Join the meeting' => __('Join the meeting', 'fluent-event'),
            'Limit seats' => __('Limit seats', 'fluent-event'),
            'Load more' => __('Load more', 'fluent-event'),
            'Location' => __('Location', 'fluent-event'),
            'Log in to RSVP' => __('Log in to RSVP', 'fluent-event'),
            'Meeting link' => __('Meeting link', 'fluent-event'),
            'Name' => __('Name', 'fluent-event'),
            'No past events.' => __('No past events.', 'fluent-event'),
            'No upcoming events yet.' => __('No upcoming events yet.', 'fluent-event'),
            'None — announcement only' => __('None — announcement only', 'fluent-event'),
            'Not going' => __('Not going', 'fluent-event'),
            'Online' => __('Online', 'fluent-event'),
            'Online event' => __('Online event', 'fluent-event'),
            'Open — anyone who can see the post registers with name and email' => __('Open — anyone who can see the post registers with name and email', 'fluent-event'),
            'Past' => __('Past', 'fluent-event'),
            'Please fill in the required fields.' => __('Please fill in the required fields.', 'fluent-event'),
            'Post' => __('Post', 'fluent-event'),
            'Post description' => __('Post description', 'fluent-event'),
            'Post title' => __('Post title', 'fluent-event'),
            'Publish event' => __('Publish event', 'fluent-event'),
            'Publish now' => __('Publish now', 'fluent-event'),
            'RSVP — logged-in members say going or not going' => __('RSVP — logged-in members say going or not going', 'fluent-event'),
            'Register' => __('Register', 'fluent-event'),
            'Register to get the meeting link.' => __('Register to get the meeting link.', 'fluent-event'),
            'Registered' => __('Registered', 'fluent-event'),
            'Registration' => __('Registration', 'fluent-event'),
            'Remove' => __('Remove', 'fluent-event'),
            'Replace image' => __('Replace image', 'fluent-event'),
            'Schedule post (optional)' => __('Schedule post (optional)', 'fluent-event'),
            'Seats' => __('Seats', 'fluent-event'),
            'Show how many guests are going' => __('Show how many guests are going', 'fluent-event'),
            'Something went wrong. Please try again.' => __('Something went wrong. Please try again.', 'fluent-event'),
            'Starts' => __('Starts', 'fluent-event'),
            'This event has ended' => __('This event has ended', 'fluent-event'),
            'This event is not available.' => __('This event is not available.', 'fluent-event'),
            'Timezone' => __('Timezone', 'fluent-event'),
            'Upcoming' => __('Upcoming', 'fluent-event'),
            'Upload image' => __('Upload image', 'fluent-event'),
            'Uploading...' => __('Uploading...', 'fluent-event'),
            'Venue' => __('Venue', 'fluent-event'),
            'What members see in the space feed, above the event.' => __('What members see in the space feed, above the event.', 'fluent-event'),
            'You are registered' => __('You are registered', 'fluent-event'),
        ];
    }
}
