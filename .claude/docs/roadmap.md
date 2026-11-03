# FluentEvent — Release Roadmap

Drafted 2026-10-26. A proposal to decide on, not a commitment.

Compared against: The Events Calendar + Event Tickets (TEC), Modern Events Calendar (MEC),
Eventin, Amelia, Tickera, the hosted platforms Luma, Eventbrite and Meetup, and our sibling
FluentBooking (its landing pages are the model for v2.0).

Our angle: community-first (events live inside FluentCommunity spaces) and built on the
Fluent suite. Pick features that deepen that, and lean on sibling plugins (FluentCart,
FluentCRM) instead of rebuilding them.

`schema ready` = the table/column already exists, so no migration is needed.
Spec refs (FR-6.x) point to `.claude/docs/functional-requirements/events.md`.

Versioning: a major theme bumps the first number (v2.0 = landing pages). Promote a later
v2.x to v3.0 (e.g. tickets) if it should be marketed as major.

⭐ = Pro feature (in `fluent-event-pro`). Everything without a star is free.

---

## Release 1 — v1.0 (shipping now)

Already built:
- Events created as FluentCommunity posts; a calendar per space
- Entry modes: RSVP (going / not going), open (guest name + email, no login; form pending) and none (view-only)
- Guest management: add, edit, remove, check-in status, guest emails
- Reminder emails (scheduled per event)
- Add to calendar (.ics + Google Calendar link)
- Admin list / grid / calendar views with filters; portal timeline + calendar
- Dashboard (guest stats + trends); team permissions

---

## Release 2 — v1.1 "Seats & waitlist"

Limited-seat RSVP events for community meetups. All logged-in members, no public page needed.

| Feature | Seen in | Notes |
|---|---|---|
| Capacity limit | Luma, Meetup | max "going" guests; empty = unlimited (today's behaviour). Shows "12 / 50 going"; button turns to "Full" or "Join waitlist" |
| Waitlist (seat overflow) | Luma, Eventbrite, Meetup | when full, an RSVP lands as `waitlisted` and gets a "You are on the waitlist" email (its own on/off in Guest Emails, no meeting link). No auto-promote: the host moves waitlisted guests to going by hand (one at a time or in bulk), optionally emailing the confirmation. On/off per event |
| Guest bulk actions | Luma, Eventbrite | select guests on the current page (checkbox per row + select all); change their status (optional confirmation / cancellation / waitlist email, queued per guest) or delete them. Export comes later |
| RSVP deadline | Luma, Meetup | registration closes at a set time (e.g. 2 hours before start); admins can still add guests after it |
| Registration opens at | Meetup, Eventbrite | RSVP stays closed until a set time ("opens Monday 10:00"); pairs with the deadline |

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

## Release 3 — v1.2 "Venues, organizers, speakers & categories"

Reusable records instead of retyping them in every event. Designed as WordPress taxonomies
on `fe_event` with extra fields in term meta (FR-6.17) — not registered yet.

| Feature | Seen in | Notes |
|---|---|---|
| Reusable venues: save once (name, address, city), pick in any event; map preview + address lookup | TEC, MEC, Eventin | `fe_venue`; FR-6.2. Today each event stores its own address — that stays as a one-off option, so no migration |
| Organizers: name, email, phone, website, logo; one or more per event | TEC, MEC, Eventin | `fe_organizer`; display/credit only — who can manage an event stays with team permissions |
| Speakers: name, photo, designation, social links; one or more per event | Eventin, MEC | `fe_performer`; thin by design — no profile page |
| Categories: Workshop, Meetup, Webinar, Conference… with a color and icon; one or more per event, optional parent | TEC, MEC, Eventin | `fe_event_type` (the spec calls it "event type"; shown as Categories so it isn't confused with `entry_type` or `venue_type`) |
| Shown on the event (community post + admin), filter events by venue / organizer / speaker / category | TEC | v2.0 adds public venue / organizer / category pages |

---

## Release 4 — v1.3 "Approval & FluentCRM"

| Feature | Seen in | Notes |
|---|---|---|
| Guest approval ("request to join") | Luma | FR-6.9. When on, an RSVP lands as `pending`; the host approves or declines from the guest list (bulk too). Email on approve (with the meeting link) and on decline. An approval takes a seat — if the event is full, the guest goes to the waitlist |
| FluentCRM tag on RSVP | Fluent suite | on "going": add the contact (create if new) to chosen tags / lists. Optional: remove on not going / cancel. Set per calendar, override per event. Expose an "RSVP'd to event" automation trigger |

Design:
- Approval is another RSVP setting (`event_details.meta.approval`), matching the spec's
  `join_policy = approval`. New guest status `pending`.
- FluentCRM is optional: the settings only appear when FluentCRM is active.

---

## Release 5 — v2.0 "Event landing pages" (major)

Every event and calendar gets its own public page, outside FluentCommunity — the way
FluentBooking gives each calendar and event type a landing page. Turns FluentEvent from a
community add-on into a standalone event tool, and makes events shareable anywhere.

| Feature | Seen in | Notes |
|---|---|---|
| Public event page: cover, date/time, venue or online, host, description, guest count, add to calendar, share | FluentBooking, Luma, Eventbrite | own full-page template, not the theme. `fe_event` CPT stays non-public |
| Calendar page: upcoming + past events of one calendar, with its branding | FluentBooking host page, Luma calendars | built at `/{slug}/{calendar}/`: Luma-style header, search, All / Upcoming / Past, Cards / Timeline / Calendar views (default and lock from General Settings, last choice kept per browser), 10 per page. Public and unlisted calendars open; only public events list. Events don't link anywhere until the event page lands. Logo / cover uploads are still to come; the page uses `theme_color`. |
| Register on the page without an account (name + email) | Luma, Eventbrite | schema ready: guest rows allow no `user_id`. Public endpoint needs nonce + rate limit + honeypot. Capacity, waitlist, deadline and approval from v1.1–v1.3 apply here too |
| Confirmation page + "manage my RSVP" link (change / cancel) | FluentBooking, Luma | signed per-guest link in the confirmation email |
| Visibility: public / unlisted (link only) / private | Luma | FR-6.5 — only takes effect here: `public` is listed, `unlisted` stays off the page but opens by direct link, `private` has no public page. The admin already sets status; fcom-created events and calendars start `private` |
| Reveal the meeting link only X minutes before start | — | until then going guests see "Link available 15 min before start"; stops the link spreading early. Applies to the community post too |
| SEO + sharing: schema.org Event, Open Graph / social card, custom slug | TEC, Eventbrite | was out of scope for the fcom-only release |
| Embed: `?embedded` iframe mode, Gutenberg block, shortcode | FluentBooking, TEC | FR-6.12 |
| Landing page settings: on/off, branding | FluentBooking | global, overridable per calendar. The URL slug is already in General Settings |

URLs, matching FluentCommunity: pretty by default under the portal slug
(`/calendars/{calendar}/{event}`). The slug is set in General Settings, and
`FLUENT_EVENT_PORTAL_SLUG` overrides it.

Standalone creation (D-7) is already in: the admin creates calendars and events, so events can
exist without FluentCommunity. Events from a public FluentCommunity space get a landing page too.

---

## Release 6 — v2.1 "Registration questions"

Its own module: hosts ask custom questions when guests register — in the community RSVP and
on the v2.0 public page. Schema ready: questions in `event_details.meta.form_fields`,
answers in `guests.meta.form_response` (FR-6.6).

| Feature | Seen in | Notes |
|---|---|---|
| Form builder per event: text, textarea, dropdown, radio, checkbox, number, date, phone, consent / terms; required + help text | Luma, Event Tickets, FluentBooking | |
| Answers shown in the guest list and guest drawer; admin can edit them | Luma | |
| Default form per calendar, copied into new events | FluentBooking | |
| One form for both entry points: community RSVP and the public page | — | |
| Optional: use a Fluent Forms form instead | FluentBooking | FluentBooking already integrates Fluent Forms |

---

## Release 7 — v2.2 "Guest tools"

| Feature | Seen in | Notes |
|---|---|---|
| ⭐ Guest export (CSV, includes registration answers) | TEC, Luma | |
| Guest import / invite by email | TEC, Luma | FR-6.7 — keep or drop? |
| Clone event | MEC, Eventin | FR-6.13 |

---

## Release 8 — v2.3 "Tickets & payments" ⭐ Pro

Paid tickets sold through FluentCart, as a new entry type `ticketed`. FluentEvent owns the
tickets and seats; FluentCart owns the money — checkout, coupons / discounts, tax, invoices,
cancel / refund and sales reports. Same pattern FluentCommunity uses for paid spaces (a
FluentCart product integration, `fluent-community/Modules/Integrations/FluentCart/`).

Schema ready: `ticket_types` (per event: name, description, price, capacity `slot`, `sold`,
min / max per order, sale window, status, sort), `guests.ticket_type_id`, an order = guest
rows sharing `group_id`, payment reference on the `is_buyer` row.

| Feature | Seen in | Notes |
|---|---|---|
| ⭐ Ticket types per event (General, VIP, Early bird…): price, capacity, min / max per order, sale window, sort order | all | FR-6.4 |
| ⭐ Several tickets in one order: the buyer picks a quantity per type | all | one guest row per ticket; the buyer row holds the payment reference. Attendee name per ticket optional (defaults to the buyer) |
| ⭐ Checkout via FluentCart: the event is a FluentCart product, each ticket type a variation, synced when the host saves | FluentCommunity paywalls | FluentCart stock stays off — seats live only in `ticket_types.slot` / `sold`, so there is one inventory |
| ⭐ Seat hold during checkout (e.g. 15 min), released on failed / abandoned payment | Eventbrite | stops two buyers paying for the last seat |
| ⭐ Coupons, discounts, tax, invoices, receipts | — | FluentCart |
| ⭐ Cancel / refund in FluentCart → tickets cancelled, seats freed | — | `fluent_cart/order_canceled`, `order_fully_refunded`, `order_partially_refunded` |
| ⭐ Guests may cancel until X hours before start | — | an offset before the start like the registration window (`meta.cancel_until`, `{value, unit}`). After the cutoff a guest can no longer cancel or request a refund themselves; admins can still change a guest |
| ⭐ Sales reports | — | FluentCart; FluentEvent keeps attendance |
| ⭐ Ticket email with QR code + QR scan check-in | Event Tickets Plus, Tickera, Luma | phone camera, no app needed |

Design:
- **Ticket types belong to one event — not shared across events** (recommended). Capacity,
  sold count and sale window are that event's inventory; `ticket_types.event_id` cascades
  with the event; matches "no inheritance between events". Reuse by copying: a default
  ticket set per calendar, Clone event (v2.2) and recurring occurrences (v2.4) copy tickets.
- Free tickets (price 0) — decide: register directly like RSVP, or still go through
  FluentCart for one flow.
- Sold out + waitlist (v1.1): a waitlisted guest gets a time-limited offer to buy, not an
  automatic seat.
- Partial refund: the admin chooses which tickets in the order to cancel.
- The spec (FR-6.4) still says Paymattic — update it to FluentCart.

---

## Release 9 — v2.4 "Recurring & online"

| Feature | Seen in | Notes |
|---|---|---|
| ⭐ Recurring events | TEC Pro, MEC | FR-6.16 designed: RRULE on master, each occurrence owns its row |
| ⭐ Zoom / Google Meet auto-create links | Eventin, Amelia | FR-6.3; today hosts paste a link |
| Sponsors (name, logo, URL, level) | MEC, TEC | a taxonomy like organizers (`fe_sponsor`), deferred in the spec |

---

## Release 10 — v2.5 "Email customization" ⭐ Pro

Today the email bodies are fixed strings; only the sender, footer, on/off switches and
reminder times are configurable. By this release every guest email exists (confirmation,
cancellation, reminder, waitlist, approval, tickets), so templates cover them all at once. FluentBooking does the same per event type
(`email_notifications` meta on `CalendarSlot`).

Free keeps today's global email settings: sender name / email, reply-to, footer, the
on/off switches and reminder times.

| Feature | Seen in | Notes |
|---|---|---|
| ⭐ Global templates: edit the subject and body of each guest email | FluentBooking, TEC | smart codes such as `{{guest.name}}`, `{{event.title}}`, `{{event.start}}`, `{{event.meeting_link}}` |
| ⭐ Preview + send a test email | FluentBooking | |
| ⭐ Per-event override: the event's own subject / body, or an extra message added to the default | FluentBooking, Luma | stored on the event |
| ⭐ Per-event reminder times | Luma | read side exists (`EventReminderService::reminderFor()`); needs the UI and the save |

---

## Release 11 — v2.6 "SMS notifications (Twilio)" ⭐ Pro

Guest messages by text as well as email, through the site owner's own Twilio account.
FluentBooking keeps Twilio SMS in Pro too (`fluent-booking-pro/app/Services/Integrations/Twilio/`).

| Feature | Seen in | Notes |
|---|---|---|
| ⭐ SMS via Twilio: every guest email (confirmation, cancellation, reminder, waitlist, approval, tickets) also goes out as an SMS when the guest gave a phone number | FluentBooking Pro | site owner's own Twilio account (`account_sid`, `auth_token`), same pattern as `fluent-booking-pro/app/Services/Integrations/Twilio/`. Sent through Action Scheduler like email |
| ⭐ Phone number + SMS consent on registration (community RSVP and public page) | — | optional field with an "I agree to receive text messages" checkbox; stored in `guests.meta` (no migration); SMS only with consent |
| ⭐ SMS templates per message, with the same smart codes as email | FluentBooking Pro | short text; per-event override like email |

---

## Release 12 — v2.7 "Grow & integrate"

| Feature | Seen in | Notes |
|---|---|---|
| ⭐ FluentCRM for tickets: tags on purchase, more automation triggers | — | extends the v1.3 RSVP integration |
| Import events from iCal / Google / Eventbrite | TEC Event Aggregator | |
| Per-event insights (page views, sign-ups, conversion) | Luma | FR-6.11; landing pages give the view count |
| Outgoing webhooks | Eventbrite | for Zapier-style automation |
| Post-event feedback survey | Luma | |

---

## Release 13 — v2.8 "Notifications"

In-app notifications for admins only (site admins and team members who manage events), plus
admin-to-guest messages. Schema ready: `notifications` (the record) + `notification_users` (delivery and
per-user preferences in one table). Guests and community members get email only, as today.

| Feature | Seen in | Notes |
|---|---|---|
| Admin notifications: new RSVP, cancellation, waitlist join, approval request, ticket sold | Luma | in the notification bell |
| Per-admin notification preferences (turn each kind on / off) | Luma | schema ready: `notification_users` pref rows |
| ⭐ Message guests: one email to going / waitlisted / all guests | Luma | reuse the guest email pipeline |

---

## Later / maybe

Not scheduled yet:
- Calendar subscribe feed (iCal URL per calendar) — we already build `.ics` for single events

Big or niche — only on clear demand:
- Seat maps (TEC Elite, MEC add-on)
- Apple / Google Wallet passes
- Dedicated mobile check-in app (QR in the browser comes first, v2.3)
- Attendance certificates

## Not planned (by design)

- Per-event currency — one global currency setting
- A separate registrations table — a guest row is the ticket; an order is a `group_id`
- A separate "limited seats" entry type — capacity, waitlist, deadline and approval are RSVP settings
