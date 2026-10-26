=== FluentEvent - Community Events, RSVPs & Reminders for FluentCommunity ===
Contributors: techjewel, wpmanageninja
Donate link: https://fluentevent.com
Tags: events, calendar, rsvp, community, fluentcommunity
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create events inside your FluentCommunity spaces, collect RSVPs, and keep guests informed with confirmations and reminders.

== Description ==

FluentEvent adds events to [FluentCommunity](https://wordpress.org/plugins/fluent-community/). Post an event in a discussion space the same way you post anything else; members see it in the feed and on the space's events page, and RSVP with one click.

**FluentEvent requires FluentCommunity.** Install and activate FluentCommunity (the free version is enough) before activating FluentEvent.

= Events in your community =

* **Post events in discussion spaces** — admins and team members with event access create an event from the FluentCommunity post composer.
* **A calendar per space** — every space gets its own events calendar automatically.
* **In person or online** — add a venue and address, or an online meeting link.
* **Cover image, date, time and timezone** for every event.
* **Event status** — public, private or draft.
* **Events page in the portal** — members browse upcoming and past events in a timeline or calendar view.

= RSVP & guests =

* **Two entry modes**
  * **RSVP** — members mark themselves Going or Not going.
  * **Open** — no registration; everyone who can see the event sees all its details.
* **Show or hide the guest count** on each event.
* **Guest management** — add, edit and remove guests, and track who is going, not going, cancelled or checked in.
* **Meeting links stay private** — on RSVP events, only guests who are going see the online meeting link.

= Emails =

* **Registration confirmation** when a guest RSVPs Going.
* **Cancellation notice** when a guest changes to Not going.
* **Reminders** before the event starts — 24 hours before by default, with configurable lead times.
* **Sender settings** — from name and email, reply-to, and a footer for every email.
* Turn each email on or off.

= Add to calendar =

Guests add any event to Google Calendar or Apple Calendar (.ics) from the event page.

= Admin dashboard =

* All events in a list, grid or calendar view, filtered by status, calendar or date range.
* Guest statistics and a guest growth chart.

= Team permissions =

Give staff (editors, authors, contributors) access to events, guests, calendars or settings — read-only or with edit rights — without making them site administrators.

= Translation ready =

Every string is translatable (text domain `fluent-event`).

== Frequently Asked Questions ==

= Does FluentEvent work without FluentCommunity? =

Not yet. Events are created as FluentCommunity posts, so FluentCommunity must be installed and active. The free version is enough.

= Who can create events? =

Site administrators, and team members you have given event access under FluentEvent → Settings → Team.

= Can community members create events? =

No. Members see events and RSVP; creating events is limited to administrators and team members with event access.

= Who can see the online meeting link? =

On RSVP events, only guests who are going. On Open events, everyone who can see the event.

= What happens to my data if I delete the plugin? =

Your events, guests and settings are kept, so nothing is lost if you reinstall.

= Does it support paid tickets or recurring events? =

Not in this version. They are on the roadmap.

== Installation ==

1. Install and activate [FluentCommunity](https://wordpress.org/plugins/fluent-community/).
2. Upload the `fluent-events` folder to `/wp-content/plugins/`, or install the zip from Plugins → Add New → Upload Plugin.
3. Activate FluentEvent from the Plugins screen.
4. Open FluentEvent in the WordPress admin to set up email settings and team access.
5. Post your first event from the post composer in any FluentCommunity discussion space.

== Changelog ==

= 1.0.0 =
* Initial release.
* Events posted in FluentCommunity discussion spaces, with a calendar per space.
* RSVP and Open entry modes, with an optional guest count.
* Guest management: add, edit, remove, check in.
* Confirmation, cancellation and reminder emails.
* Add to Google Calendar and Apple Calendar.
* Admin dashboard with list, grid and calendar views.
* Team permissions for staff.
