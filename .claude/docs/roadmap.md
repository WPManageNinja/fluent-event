# FluentEvent — Release Roadmap

Drafted 2026-10-26. A proposal to decide on, not a commitment.
Updated 2026-11-14: Release 1 shipped (1.0.0 and 1.0.1, Nov 13, 2026).

Compared against: The Events Calendar + Event Tickets (TEC), Modern Events Calendar (MEC),
Eventin, Amelia, Tickera, the hosted platforms Luma, Eventbrite and Meetup, and our sibling
FluentBooking (its landing pages were the model for ours).

Our angle: community-first (events live inside FluentCommunity spaces) and built on the
Fluent suite. Pick features that deepen that, and lean on sibling plugins (FluentCart,
FluentCRM) instead of rebuilding them.

`schema ready` = the table/column already exists, so no migration is needed.
Spec refs (FR-6.x) point to `.claude/docs/functional-requirements/events.md`.

⭐ = Pro feature (in `fluent-event-pro`). Everything without a star is free.

---

## Release 1 (released Nov 13, 2026)

Shipped in 1.0.0:
- Calendars and events in the WordPress admin; FluentCommunity is optional
- Event details: in person (venue + address) or online (meeting link), cover image, date, time and time zone, Markdown description
- Statuses: public, unlisted (link only), private, draft
- Entry modes: RSVP (going / not going), open (Register with name + email; a member can cancel and register again) and none (view-only); guest count on / off
- Public pages, no FluentCommunity needed:
  - each event at `/event/<slug>/` (the `event` part set in General Settings → Public Pages); an unknown, private or draft event, or one in a private calendar, answers the theme's 404
  - the All Events Page: `[fluent_events]` or its block on whichever page the admin picks — every public calendar's events, a chip per calendar, search, All / Upcoming / Past, Cards / Timeline / Calendar; `[fluent_event]` or its block shows one event
  - registration without an account (name + email, rate limit + honeypot); a logged-in viewer answers by account, as on the portal (form prefilled, email editable); guests get source `wordpress_pages`
  - visibility: public is listed, unlisted opens by direct link only, private has no public page
- Guest management: add, edit, remove; going, not going, checked in, cancelled
- Guest emails: confirmation, cancellation, reminders (scheduled per event); sender settings; each on / off
- Add to calendar (.ics + Google Calendar link)
- Admin card / timeline / calendar views with filters; dashboard (guest stats + trends). Admin-only (`manage_options`); team permissions come back in Pro (Release 5)
- FluentCommunity (switched on in its Features settings): admins post events from the composer, in a space or community-wide; a calendar per space; events in the feed and on the Events pages (cards / timeline / calendar); edit an event from its post menu
- Translation ready (`fluent-event`)

1.0.1: automatic plugin updates with checksum verification, RTL support, a "View in FluentEvent" link in the community event menu.

---

## Release 2 — "Seats & waitlist"

Limited-seat RSVP events for community meetups. All logged-in members, no public page needed.
Seat features built on `release-2` (#204, #205, #206, #209, #211); the rest not started. Ships once develop is merged into it.

| Feature | Notes |
|---|---|
| Capacity limit | max "going" guests; empty = unlimited (today's behaviour). Shows "12 / 50 going"; button turns to "Full" or "Join waitlist" |
| Waitlist (seat overflow) | when full, an RSVP lands as `waitlisted` and gets a "You are on the waitlist" email (its own on/off in Guest Emails, no meeting link). No auto-promote: the host moves waitlisted guests to going by hand (one at a time or in bulk), optionally emailing the confirmation. On/off per event |
| Guest bulk actions | select guests on the current page (checkbox per row + select all); change their status (optional confirmation / cancellation / waitlist email, queued per guest) or delete them. Export comes later |
| RSVP deadline | registration closes at a set time (e.g. 2 hours before start); admins can still add guests after it |
| Registration opens at | RSVP stays closed until a set time ("opens Monday 10:00"); pairs with the deadline |
| Event status on the portal create / edit page | today every event posted from FluentCommunity is saved `private` (`FeedHandler::eventInput()`), so it never gets a public page. Let the admin pick public / unlisted / private while creating or editing; draft stays admin-only, since the post publishes. A public event still needs its space calendar to be public before its page opens — say so beside the choice |
| Share event | a Share button on the landing event page and the community event page: copy link, email, X, Facebook, LinkedIn, WhatsApp, and the phone's own share sheet where the browser has one. Shares the public page when the event has one, otherwise the community link (members only). Moved from Release 11 |
| Share panel in the admin (link + shortcode) | admins can't see today that an event or calendar can be placed on a page. A Share button on the event details page opens tabs: **Direct link** (the public page, with View and Copy; when the event or its calendar isn't public, say why and link to the setting instead of a dead URL), **Shortcode** (`[fluent_event event="<slug>"]`, Copy), **Block** (add the FluentEvent Single Event block), and **Community post** for a FluentCommunity event. The calendar details page gets the same with `[fluent_events calendar="<slug>"]` and its filtered list link. Iframe embed stays in Release 11 |
| Clone event | FR-6.13. Admin: Duplicate in the event's menu copies its content and settings (venue, entry type, capacity, waitlist, registration window, cover, description) into a new draft in the same calendar; guests are not copied. A FluentCommunity event's copy has no post until the admin publishes it to a space. Moved from Release 6 |
| Countdown clock | a live "Starts in 2d 04:13:09" on the community event page and the landing event page; counts to the registration opening or deadline while one is ahead. Today "Starts in…" is worked out on page load and never ticks, by choice; a running clock reverses that |

Design:
- **No new entry type.** Capacity, waitlist and the registration window are settings on RSVP
  events (`event_details.meta`: `capacity`, `waitlist`, `registration_opens`,
  `registration_closes`; the window is an offset `{value, unit}` before the start, so it moves
  with the event). The entry type stays "how people get in" (rsvp / open / later ticketed);
  ticketed mode reuses the same waitlist with per-tier seats.
- New guest status `waitlisted`. Waitlisted guests get no meeting link and no reminders (the
  reminder query reads `Event::SEAT_STATUSES`).
- The last seat is a race: two members RSVPing at once must not both get it. Take seats
  under a lock, not a read-then-insert.

---

## Release 3 — "Venues, organizers, speakers & categories"

Reusable records instead of retyping them in every event. Designed as WordPress taxonomies
on `fe_event` with extra fields in term meta (FR-6.17) — not registered yet.

| Feature | Notes |
|---|---|
| Reusable venues: save once (name, address, city), pick in any event; map preview + address lookup | `fe_venue`; FR-6.2. Today each event stores its own address — that stays as a one-off option, so no migration |
| Organizers: name, email, phone, website, logo; one or more per event | `fe_organizer`; display/credit only — who can manage an event stays with team permissions |
| Speakers: name, photo, designation, social links; one or more per event | `fe_performer`; thin by design — no profile page |
| Categories: Workshop, Meetup, Webinar, Conference… with a color and icon; one or more per event, optional parent | `fe_event_type` (the spec calls it "event type"; shown as Categories so it isn't confused with `entry_type` or `venue_type`) |
| Shown on the event (community post + admin), filter events by venue / organizer / speaker / category | public venue / organizer / category pages come later |
| Host on the public event page | the event's organizers, shown on the landing event page. Left out of Release 1 so the page matches the community event page |

---

## Release 4 — "Approval & FluentCRM"

| Feature | Notes |
|---|---|
| Guest approval ("request to join") | FR-6.9. When on, an RSVP lands as `pending`; the host approves or declines from the guest list (bulk too). Email on approve (with the meeting link) and on decline. An approval takes a seat — if the event is full, the guest goes to the waitlist |
| FluentCRM tag on RSVP | on "going": add the contact (create if new) to chosen tags / lists. Optional: remove on not going / cancel. Set per calendar, override per event. Expose an "RSVP'd to event" automation trigger |
| Reveal the meeting link only X minutes before start | until then going guests see "Link available 15 min before start"; stops the link spreading early. Applies to the community post and the landing event page. Moved from Release 8 |

Design:
- Approval is another RSVP setting (`event_details.meta.approval`), matching the spec's
  `join_policy = approval`. New guest status `pending`.
- FluentCRM is optional: the settings only appear when FluentCRM is active.

---

## Release 5 — "Teams & permissions" ⭐ Pro

| Feature | Notes |
|---|---|
| ⭐ Team members & permissions | Pro's Team module: give users who aren't site admins access per area, with read / write keys for calendars, events, guests and settings, plus "manage all data". A Team tab to add members and tick their keys; creating and editing events from FluentCommunity follows the events key. Free stays admin-only (`manage_options`) and checks the keys only while Pro is active. Built from the module removed from free on 2026-11-05 (`backup/permissions`); in progress on `feat/pro-team-permissions` |

---

## Release 6 — "Registration questions & guest tools"

Registration questions are a ⭐ Pro module: hosts ask custom questions when guests register —
in the community RSVP and on the public event page. Schema ready: questions in
`event_details.meta.form_fields`, answers in `guests.meta.form_response` (FR-6.6).

| Feature | Notes |
|---|---|
| ⭐ Form builder per event: text, textarea, dropdown, radio, checkbox, number, date, phone, consent / terms; required + help text | |
| ⭐ Answers shown in the guest list and guest drawer; admin can edit them | |
| ⭐ Default form per calendar, copied into new events | |
| ⭐ One form for both entry points: community RSVP and the public page | |
| ⭐ Optional: use a Fluent Forms form instead | FluentBooking already integrates Fluent Forms |
| ⭐ Guest export (CSV, includes registration answers) | |
| Guest import / invite by email | FR-6.7 — keep or drop? |

---

## Release 7 — "Tickets & payments" ⭐ Pro

Paid tickets sold through FluentCart, as a new entry type `ticketed`. FluentEvent owns the
tickets and seats; FluentCart owns the money — checkout, coupons / discounts, tax, invoices,
cancel / refund and sales reports. Same pattern FluentCommunity uses for paid spaces (a
FluentCart product integration, `fluent-community/Modules/Integrations/FluentCart/`).

Schema ready: `ticket_types` (per event: name, description, price, capacity `slot`, `sold`,
min / max per order, sale window, status, sort), `guests.ticket_type_id`, an order = guest
rows sharing `group_id`, payment reference on the `is_buyer` row.

| Feature | Notes |
|---|---|
| ⭐ Ticket types per event (General, VIP, Early bird…): price, capacity, min / max per order, sale window, sort order | FR-6.4 |
| ⭐ Several tickets in one order: the buyer picks a quantity per type | one guest row per ticket; the buyer row holds the payment reference. Attendee name per ticket optional (defaults to the buyer) |
| ⭐ Checkout via FluentCart: the event is a FluentCart product, each ticket type a variation, synced when the host saves | FluentCart stock stays off — seats live only in `ticket_types.slot` / `sold`, so there is one inventory |
| ⭐ Seat hold during checkout (e.g. 15 min), released on failed / abandoned payment | stops two buyers paying for the last seat |
| ⭐ Coupons, discounts, tax, invoices, receipts | FluentCart |
| ⭐ Cancel / refund in FluentCart → tickets cancelled, seats freed | `fluent_cart/order_canceled`, `order_fully_refunded`, `order_partially_refunded` |
| ⭐ Guests may cancel until X hours before start | an offset before the start like the registration window (`meta.cancel_until`, `{value, unit}`). After the cutoff a guest can no longer cancel or request a refund themselves; admins can still change a guest |
| ⭐ Sales reports | FluentCart; FluentEvent keeps attendance |
| ⭐ Ticket email with QR code + QR scan check-in | phone camera, no app needed |

Design:
- **Ticket types belong to one event — not shared across events** (recommended). Capacity,
  sold count and sale window are that event's inventory; `ticket_types.event_id` cascades
  with the event; matches "no inheritance between events". Reuse by copying: a default
  ticket set per calendar, Clone event (Release 2) and recurring occurrences (Release 8) copy tickets.
- Free tickets (price 0) — decide: register directly like RSVP, or still go through
  FluentCart for one flow.
- Sold out + waitlist (Release 2): a waitlisted guest gets a time-limited offer to buy, not an
  automatic seat.
- Partial refund: the admin chooses which tickets in the order to cancel.
- The spec (FR-6.4) still says Paymattic — update it to FluentCart.

---

## Release 8 — "Recurring & online"

| Feature | Notes |
|---|---|
| ⭐ Recurring events | FR-6.16 designed: RRULE on master, each occurrence owns its row |
| ⭐ Zoom / Google Meet auto-create links | FR-6.3; today hosts paste a link |
| Sponsors (name, logo, URL, level) | a taxonomy like organizers (`fe_sponsor`), deferred in the spec |

---

## Release 9 — "Email customization" ⭐ Pro

Today the email bodies are fixed strings; only the sender, footer, on/off switches and
reminder times are configurable. By this release every guest email exists (confirmation,
cancellation, reminder, waitlist, approval, tickets), so templates cover them all at once. FluentBooking does the same per event type
(`email_notifications` meta on `CalendarSlot`).

Free keeps today's global email settings: sender name / email, reply-to, footer, the
on/off switches and reminder times.

| Feature | Notes |
|---|---|
| ⭐ Global templates: edit the subject and body of each guest email | smart codes such as `{{guest.name}}`, `{{event.title}}`, `{{event.start}}`, `{{event.meeting_link}}` |
| ⭐ Preview + send a test email | |
| ⭐ Per-event override: the event's own subject / body, or an extra message added to the default | stored on the event |
| ⭐ Per-event reminder times | read side exists (`EventReminderService::reminderFor()`); needs the UI and the save |

---

## Release 10 — "SMS notifications (Twilio)" ⭐ Pro

Guest messages by text as well as email, through the site owner's own Twilio account.
FluentBooking keeps Twilio SMS in Pro too (`fluent-booking-pro/app/Services/Integrations/Twilio/`).

| Feature | Notes |
|---|---|
| ⭐ SMS via Twilio: every guest email (confirmation, cancellation, reminder, waitlist, approval, tickets) also goes out as an SMS when the guest gave a phone number | site owner's own Twilio account (`account_sid`, `auth_token`), same pattern as `fluent-booking-pro/app/Services/Integrations/Twilio/`. Sent through Action Scheduler like email |
| ⭐ Phone number + SMS consent on registration (community RSVP and public page) | optional field with an "I agree to receive text messages" checkbox; stored in `guests.meta` (no migration); SMS only with consent |
| ⭐ SMS templates per message, with the same smart codes as email | short text; per-event override like email |

---

## Release 11 — "Grow & integrate"

| Feature | Notes |
|---|---|
| ⭐ FluentCRM for tickets: tags on purchase, more automation triggers | extends the Release 4 RSVP integration |
| Import events from iCal / Google / Eventbrite | |
| Per-event insights (page views, sign-ups, conversion) | FR-6.11; landing pages give the view count |
| Outgoing webhooks | for Zapier-style automation |
| Post-event feedback survey | |
| Social card + search: `og:image` from the event cover, schema.org Event (JSON-LD), custom slug | the landing layout already sends `og:title` / `og:description` / `og:url`; without `og:image` a shared link shows no picture |
| Shortcodes, like FluentBooking's | shipped in Release 1: `[fluent_events]` (`calendar`, `view`, `filter`, `view_switch`, `show_calendars`) and `[fluent_event event=…]`, each with a matching block |
| Embed: `?embedded` iframe mode, Gutenberg block | FR-6.12. The block wraps the shortcodes |

---

## Release 12 — "Notifications"

In-app notifications for admins only (site admins), plus
admin-to-guest messages. Schema ready: `notifications` (the record) + `notification_users` (delivery and
per-user preferences in one table). Guests and community members get email only, as today.

| Feature | Notes |
|---|---|
| Admin notifications: new RSVP, cancellation, waitlist join, approval request, ticket sold | in the notification bell |
| Per-admin notification preferences (turn each kind on / off) | schema ready: `notification_users` pref rows |
| ⭐ Message guests: one email to going / waitlisted / all guests | reuse the guest email pipeline |

---

## Later / maybe

Not scheduled yet:
- Calendar subscribe feed (iCal URL per calendar) — we already build `.ics` for single events
- Landing page branding: calendar logo / cover uploads, colors; global, overridable per calendar (FluentBooking). The first landing release ships without customization

Big or niche — only on clear demand:
- Seat maps (TEC Elite, MEC add-on)
- Apple / Google Wallet passes
- Dedicated mobile check-in app (QR in the browser comes first, Release 7)
- Attendance certificates

## Not planned (by design)

- Per-event currency — one global currency setting
- A separate registrations table — a guest row is the ticket; an order is a `group_id`
- A landing confirmation page or "manage my RSVP" link — registering updates the event page in place, and visitors have no logged-in experience to manage it from
- A separate "limited seats" entry type — capacity, waitlist, deadline and approval are RSVP settings
