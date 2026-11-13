=== FluentEvent - Event Calendar, RSVP & Registration ===
Contributors: techjewel, wpmanageninja
Tags: events, event calendar, rsvp, event registration, fluentcommunity
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
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

For RSVP and Open events, the online meeting link shows only after a guest registers. You can show or hide the guest count on each event.

= Event pages =

* **A page for each event** at `/event/<event-slug>/`. You can change the `event` part in the settings.
* **An event list** — add the `[fluent_events]` shortcode or the Fluent Events block to any page.
* **A single event** — add the `[fluent_event]` shortcode or the Fluent Event block.
* **Add to calendar** — guests add an event to Google Calendar, or to Apple Calendar or Outlook with an `.ics` file.

= Guests =

* Add, edit and remove guests.
* See who is going, not going, checked in or cancelled.

= Emails =

* **Registration confirmation** when a guest registers.
* **Cancellation notice** when a guest cancels.
* **Reminders** before the event starts. The default is 24 hours before. You can change the times.
* **Sender settings** — from name and email, reply-to and an email footer.
* Turn each email on or off.

= Admin dashboard =

* All events in a card, timeline or calendar view, with filters for calendar and date range.
* Guest statistics and a guest trend chart.

= FluentCommunity integration =

If you use [FluentCommunity](https://wordpress.org/plugins/fluent-community/), turn on FluentEvent in FluentCommunity's Features settings. Then:

* Site administrators post events in discussion spaces from the post composer.
* Each space gets its own events calendar.
* Members see events in the feed and on the space's events page.

FluentEvent does not need FluentCommunity. Without it, all other features work.

= Translation ready =

All strings are translatable. The text domain is `fluent-event`.

= Source code =

The plugin includes the uncompiled source of its JavaScript and styles in the `resources` folder. To build the files in `assets`, run `npm install` and then `npm run build`.

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

== Changelog ==

= 1.0.1 - Nov 13, 2026 =
- Added: Automatic updates from the Plugins screen. FluentEvent installs an update only when the download matches its SHA-256 checksum
- Added: Right-to-left (RTL) support. On Arabic, Hebrew and other RTL sites, the admin, the event pages and the FluentCommunity events pages use a mirrored layout
- Added: A View in FluentEvent item in the event menu of the FluentCommunity portal, for site administrators

= 1.0.0 - Nov 13, 2026 =
- Initial release
- Calendars to group your events
- Events in person or online, with a cover image, date, time and time zone
- Four event statuses: Public, Unlisted, Private and Draft
- Three entry modes: RSVP, Open registration and View only
- A public page for each event, an event list, and shortcodes and blocks to show events on any page
- Add to Google Calendar, Apple Calendar and Outlook
- Guest management: add, edit and remove guests, and see who is going, not going, checked in or cancelled
- Emails: registration confirmation, cancellation notice and event reminders, with sender settings
- An admin dashboard with card, timeline and calendar views, guest statistics and a guest trend chart
- FluentCommunity integration: events in discussion spaces, a calendar for each space, and events in the feed
- Translation ready
