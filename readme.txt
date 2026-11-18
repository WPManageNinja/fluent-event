=== FluentEvent - Event Calendar, RSVP & Registration ===
Contributors: techjewel, wpmanageninja
Tags: events, event calendar, rsvp, event registration, fluentcommunity
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create events, collect RSVPs and registrations, and send confirmations and reminders to your guests.

== Description ==

FluentEvent adds events to your WordPress site. You create events in the WordPress admin, group them in calendars, and publish each event on its own page. Visitors register with their name and email. FluentEvent sends each guest a confirmation and a reminder before the event starts.

= Events =

* **Calendars** — group your events in calendars.
* **In person or online** — add a venue and an address, or an online meeting link.
* **Cover image, date, time and time zone** for each event.
* **Four statuses** — Public, Unlisted (not in the event list, but the direct link works), Private (no public page) and Draft.

= Registration =

Each event has one of three entry modes:

* **RSVP** — guests tell you that they are going.
* **Open** — guests click Register and give their name and email.
* **View only** — nobody registers. Every visitor sees the full event, including the meeting link.

For RSVP and Open events, the online meeting link shows only after a guest registers.

* **Capacity** — set how many guests can be going at once. A full event shows "Event full" and takes no new registrations. Admins can still add guests.
* **Guest count and spots left** — show how many guests are going, how many spots are left, or both as a progress bar.
* **Going or not going** — guests answer going or not going. A guest who said not going can register again with the same email. An email that is already registered gets a clear warning.

= Event pages =

* **A page for each event** at `/event/<event-slug>/`. You can change the `event` part in the settings.
* **An event list** — add the `[fluent_events]` shortcode or the Fluent Events block to any page.
* **A single event** — add the `[fluent_event]` shortcode or the Fluent Event block.
* **Add to calendar** — guests add an event to Google Calendar, or to Apple Calendar or Outlook with an `.ics` file.

= Guests =

* Add, edit and remove guests.
* See who is going, not going, checked in or cancelled.
* Cancel a guest's registration. Only an admin can cancel, and the guest can't undo it.

= Emails =

* **Registration confirmation** when a guest registers.
* **Cancellation notice** when a guest says not going or you cancel their registration.
* **Reminders** before the event starts. The default is 24 hours before. You can change the times.
* **Sender settings** — from name and email, reply-to and an email footer.
* Turn each email on or off.

= Admin dashboard =

* All events in a card, timeline or calendar view, with filters for calendar and date range.
* Guest statistics and a guest trend chart.
* A Settings tab on each event with its entry type, capacity and display options.
* Works on phones, including the calendar.

= FluentCommunity integration =

If you use [FluentCommunity](https://wordpress.org/plugins/fluent-community/), turn on FluentEvent in FluentCommunity's Features settings. Then:

* Site administrators post events in discussion spaces from the post composer.
* Each space gets its own events calendar.
* Members see events in the feed and on the space's events page.
* Site administrators get a menu on the community Events page with links to the FluentEvent dashboard, calendars and settings.

FluentEvent does not need FluentCommunity. Without it, all other features work.

= Translation ready =

All strings are translatable. The text domain is `fluent-event`.

== Frequently Asked Questions ==

= Does FluentEvent need FluentCommunity? =

No. FluentEvent works on its own. The FluentCommunity integration is optional.

= Who can create and manage events? =

Site administrators. The FluentEvent admin is for administrators only.

= Can visitors register without an account? =

Yes. On the event page, visitors register with their name and email.

= Who can see the online meeting link? =

On RSVP and Open events, only guests who registered. On View only events, every visitor.

= What happens to my data if I delete the plugin? =

FluentEvent keeps your events, guests and settings. If you install the plugin again, your data is still there.

= Does it support paid tickets or recurring events? =

Not in this version. Both are on the roadmap.

== Installation ==

1. In Plugins → Add New, search for FluentEvent, then click Install Now. Or upload the `fluent-event` folder to `/wp-content/plugins/`.
2. Activate FluentEvent on the Plugins screen.
3. Open FluentEvent in the WordPress admin. Set the email settings and create your first calendar and event.
4. Optional: add the `[fluent_events]` shortcode or the Fluent Events block to a page to list your events.
5. Optional: if you use FluentCommunity, turn on FluentEvent in FluentCommunity's Features settings.

== Source code ==

The files in `assets/` are compiled. The uncompiled source (the Vue, JavaScript and SCSS files) and the build setup are on GitHub: https://github.com/WPManageNinja/fluent-event

To build the files in `assets/` again:

1. Install Node.js 18 or later.
2. Clone the repository. In its folder, run `npm ci`.
3. Run `npm run build`.

== External services ==

FluentEvent connects to two external services: the WPManageNinja update server, automatically, and Google Calendar, only when a person clicks a link.

= WPManageNinja update server =

When WordPress checks for plugin updates, when an admin opens the plugin's details or clicks Check Update, and when an update is installed, FluentEvent contacts the WPManageNinja update server (fluentapi.wpmanageninja.com). The version check sends the plugin slug, the installed version and the site URL. The update server uses them to find the new version and its download link. The update package is then downloaded from the link that the server returns.

* Terms and Conditions: https://wpmanageninja.com/terms-and-conditions/
* Privacy Policy: https://wpmanageninja.com/privacy/

= Google Calendar =

The event page and the registration confirmation show an "Add to Google Calendar" link. FluentEvent sends no data to Google on its own. When a person clicks the link, their browser opens Google Calendar with the event's title, start and end time, description, location and page URL in the link, so Google can create the calendar entry.

* Terms of Service: https://policies.google.com/terms
* Privacy Policy: https://policies.google.com/privacy

The other "Add to calendar" option downloads an .ics file that the site creates. It sends no data to any service.

== Changelog ==

= 1.1.0 (Date: Nov 18, 2026) =
- Added: Guest Capacity Limit for RSVP and Open Events
- Added: Show How Many Spots Are Left
- Added: Settings Tab on the Admin Event Page
- Added: Admin Menu on the Community Events Page Header
- Added: Phone Calendars in the Community and Admin
- Updated: Guests Answer Going or Not Going
- Updated: Only Admins Cancel a Registration
- Updated: Registering Again Brings Back a Not-Going Guest
- Improved: Warning When an Email Is Already Registered
- Improved: Community Events Page Header Matches Spaces and Courses
- Improved: Phone Month Calendar Shows a Tapped Day's Events
- Improved: Event Covers and Timeline Kept on Phones
- Fixed: Events Unreachable From the Public Month Calendar on Phones
- Fixed: Event List Running Off Small Phone Screens
- Fixed: Admin Layout Overflows on Phones
- Fixed: Delete Button Beside the Danger Zone Input
- Security: Visitor Forms Can't Change a Member's Registration

= 1.0.5 (Date: Nov 17, 2026) =
- Added: Live Countdown to an Event's Start
- Added: Guest Count on Admin Event Cards
- Added: Edit and Share Buttons in Event Details
- Added: Share Event Modal With the Link, Shortcode and Block
- Improved: Guest Count in the Event Page Overview Tab
- Improved: Required Field Marks in the Add Guest Form
- Improved: Event Drawer Rows in the Event Page Order
- Fixed: Unstyled Flash When the Admin Page Opens
- Fixed: Tables Not Created on Older MySQL and MariaDB
- Fixed: Meta Table Not Created on MyISAM Databases
- Fixed: Missing Tables Not Created Again After an Update
- Fixed: Community Event Post Left Without Its Event
- Fixed: Event Cover Missing in Community Card View
- Fixed: Community Time Picker Rows Misaligned
- Fixed: Portal Calendar and Date Picker Dates Not Translated
- Fixed: Link Button Text Hidden on Focus
- Fixed: PHP Deprecation Notice on the Admin Page
- Fixed: Events Page Error on a Bad Calendar Link
- Security: Public Registration Forms No Longer Show Who Registered
- Security: Guest Emails Greet Visitors Without Their Typed Name
- Security: More Reliable Rate Limit on Public Registration
- Security: Stricter Sanitizing of Email Notification Settings

= 1.0.1 (Date: Nov 13, 2026) =
- Added: Automatic Plugin Updates With Checksum Verification
- Added: RTL Language Support
- Added: View in FluentEvent Link in the Community Event Menu

= 1.0.0 - Nov 13, 2026 =
- Initial release
