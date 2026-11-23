# FR-10: FluentCommunity Integration

**Summary:** With FluentCommunity (fcom) active, an event is a **post** in a discussion space.
The post carries `content_type = 'event'` and `meta.event_id`; everything about the event lives in
FluentEvent's tables. FluentEvent pushes all of it into fcom through hooks; fcom only gains a few
event-agnostic extension points. Without fcom, FluentEvent runs standalone and nothing below loads.

This is the record of intended behaviour. Keep it in step with `Modules/FluentCommunity/` and
`resources/fcom-portal/`; the tests listed at the end verify it.

## Ownership

| Concern | Owner | Where |
|---|---|---|
| Post title, message, comments, reactions, topics, schedule | fcom | `fcom_posts` (`content_type = 'event'`, `meta.event_id`) |
| Event title, description, dates, venue, cover, entry type, guest-count switch | FluentEvent | `wp_posts` (`fe_event`) + `fe_event_details.meta` |
| Space ↔ calendar link | FluentEvent | `fe_calendars.meta.fcom_space_id`; the one Community calendar for community-wide events has `fcom_global = true` ([details](fcom-community-wide-events.md)) |
| Event → post link | FluentEvent | `fe_meta` row: `object_type=event`, `meta_key=fcom_feed_id` (the events list joins on it) |
| Guests | FluentEvent | `fe_guests` (`source = 'fluent_community'`, `source_id` = post id) |

Post and event titles/descriptions are separate. Editing the post uses fcom's normal edit modal.
The event's "⋮" menu shows site administrators **Edit Event** (the portal edit page) and
**View in FluentEvent** (the event in the FluentEvent admin, where its guests are managed).

## Bridge (`Modules/FluentCommunity/`)

Opt-in. fcom lists FluentEvent under **Settings → Features → Add-ons**; its **View Settings**
drawer holds one **Enable FluentEvent** checkbox (fcom option `_fluent_event_settings`, off by
default). Only while it is on does fcom fire `fluent_community/fluent_event/init`, and the bridge
registers on that action. While it is off, FluentEvent adds nothing to fcom: no menu item, space
tab, composer option, portal pages or assets, and `CommunityPolicy` refuses every `community/*`
route. Event posts already in a feed stay as plain posts with no event card. The FluentEvent admin
keeps listing community calendars and events; turning the switch back on restores everything.

The same drawer holds the **Events URL slug** (`route_slug`, default `events`). fcom rejects a slug
that is empty, already one of its portal route paths, or one of its space child paths, and passes
its settings with the init action (`$app, $settings`). Every community events URL hangs off that
slug; `fluent_event/community/route_paths` can still move any single route:

| Key | Default path | Route |
|---|---|---|
| `events` | `{slug}` | `fe_events` |
| `create_event` | `{slug}/create` | `fe_create_event` |
| `event_view` | `{slug}/:event_slug` | `fe_global_event_view` |
| `space_events` | `{slug}` under `space/:space/` | `fe_space_events` |
| `space_event_view` | `{slug}/:event_slug` under `space/:space/` | `fe_event_view` |

- **PortalHandler**
  - An **Events** item in fcom's Menu Settings, off until an admin enables it; the mobile menu
    places it before Profile. It is supplied on every request through the bridge's
    `fluent_community/default_main_menu_items`; fcom keeps the admin's saved label and order and
    drops the saved row when nothing supplies it, so the link leaves with the plugin.
  - A space **Events** tab for discussion spaces only, off until a space admin enables it.
  - The first segment of each top-level route in fcom's route paths, so a reload serves the
    portal; the menu item links to the `events` path. The route paths, the portal script and styles are printed at
    `fluent_community/before_js_loaded` (the portal is headless, so enqueues never print).
- **FeedHandler** — the event-post lifecycle:
  - `new_feed_data_type_event`: requires a site administrator (`manage_options`), a discussion space
    or no space at all (community-wide, only while fcom's global posts are on), and valid event
    input; strips media. Vetoes return `WP_Error` (a thrown exception becomes a bare HTTP 500 in fcom).
  - `just_created_type_event`: finds or creates the space calendar (or the Community calendar for a
    post with no space), claims uploaded cover and
    description images, creates the event (status `private`, author = post author), writes
    `meta.event_id` and the `fcom_feed_id` link. If the event can't be created, the post is deleted.
  - `update_feed_data_type_event` / `update_data`: keep the event link through edits (fcom
    rebuilds post meta); block moves to non-discussion spaces, and moves to the profile while
    fcom's global posts are off.
  - `feed/updated`: a post moved to another space, or out of any space, moves its event to that
    space's calendar (or the Community calendar), and the guests follow.
  - `fluent_event/event/calendar_changed`: an event moved to another calendar in the admin moves
    its post to that calendar's space, or out of any space for the Community calendar; if the
    calendar is a site calendar, the post lets go of the event and becomes a plain post.
  - `feed/before_deleted`: deleting the post keeps the event. It drops the post link and parks
    the event as a `draft` with its guests, so its reminders stop and an event manager decides
    whether to delete it.
  - `fluent_event/event/before_deleted` (priority 5, before cleanup): deleting the event in the
    admin turns the post back into a plain post.
  - `feed_general_config` + `rendering_feed_model`: batch-load events and attach the portal
    payload as `feed.fluent_event`; registration is closed on a post that isn't published or unlisted.
- **SpaceHandler** — `space/before_delete` parks the space calendar the way a deleted post parks
  its event: every event in it becomes a `draft` with its guests and loses its post link, and the
  calendar becomes a `draft` with no space. Nothing is deleted; an admin decides that in FluentEvent.
  (fcom bulk-deletes the posts without per-post hooks, so `feed/before_deleted` never runs here.)

## Portal UI (`resources/fcom-portal/`)

- `fcom-portal.js` registers routes through fcom's `fluent_com_portal_routes`, taking every path
  from the printed `route_paths` (by default `/events`, `/events/create`, `/events/:event_slug` for
  a community-wide event, and two children of fcom's space route, `/space/:space/events` and
  `/space/:space/events/:event_slug`). Links use route names, never paths. The event page renders under the space's header and tabs (at
  the top level when community-wide) and
  is addressed by the event's own slug (`wp_posts.post_name`, as on the public page), not the
  post's: `GET community/events/by-slug?space=&slug=` returns the post's slug, and the page then
  reads the event through fcom's `feeds/{slug}/by-slug`, so it is visible exactly when its post
  is. Under the title it says who posted it and where ("Posted by {name} in {space}", or "on their
  profile" when community-wide) with a **View post** link to fcom's `single_feed`, from that same
  response. Back buttons are `router.back()`. The create page starts with **Where do you want to post
  this event?** — Community-wide (the composer posts to fcom's "On my profile") or In a space
  (the composer's space picker, spaces only); In a space is preselected, with the space picked when
  opened with `?space=slug`. Community-wide is disabled while fcom's global posts are off. It also registers the event card for `content_type = 'event'`, and
  a **Create new event** item in two "…" menus, both for site administrators: the space header
  (spaces they may post in, opening with `?space=`) and the portal feed home (no space). For event
  posts it turns the composer's attachment apps off and removes "On my profile" from the picker.
  On All Events, a line above each event's title shows its space's icon and name, or "Community-wide".
- The pages are a separate Vue + Element Plus app loaded on first visit (fcom exposes neither
  to add-ons). The card is a string template on fcom's own Vue.
- REST, `fluent-event/v2/community/*`:
  - `GET events` — the events list, filtered to visible posts in SQL.
  - `POST events/{id}/register` — RSVP `going` or `not_going`; rate-limited to 10 per minute.
  - `GET editor-assets` — the WordPress editor markup for the create-event page; site administrators only.

## fcom extension points (event-agnostic)

| JS filter | Where | Used for |
|---|---|---|
| `fluent_com_feed_content_components` | `Feed.vue`, `RenderChildPost.vue` | Render a post body by `content_type` |
| `fluent_com_space_actions` | `_SpaceMenu.vue` | Extra items in the space "…" menu |
| `fluent_com_composer_config` | `CreateStatusForm.vue` (+ `mediaApp`, `giphyApp` flags) | Turn composer apps off per post type |

## Rules

- **Who creates events:** site administrators (`manage_options`) who may also post in the space. The free plugin
  has no team permissions; per-module access is planned for Pro (kept on the `backup/permissions` branch).
- **Calendars:** created lazily on the first event, named and slugged after the space, status
  `private` (no public page). Admins may change the status later. After that the calendar's title
  and slug are its own: editing the space leaves them alone. Community-wide events share one
  `Community` calendar, created the same way.
- **Statuses:** events are `public | unlisted | private | draft`, calendars `public | private | draft`. fcom-created events
  start `private`, like their calendars. Both statuses show in the admin and can be changed there.
  The portal shows every status except `draft`, which is never returned. `unlisted` only takes
  effect on the upcoming landing page.
- **Capacity:** `meta.capacity` caps `going` + `checked_in` guests on `rsvp` and `open` events (0 = unlimited).
  A full event shows **Event full** in place of the RSVP or Register button and refuses a new one (422); a guest
  already going keeps their seat and can still give it up, and admins can add guests past it. Two switches decide
  what guests see: **Show how many guests are going** and **Show how many spots are left** (off by default, only
  with a capacity). Both on: a meter ("7 out of 10 going", "3 spots left"); count only: "7 going"; spots only:
  "3 spots left"; neither: nothing until it fills. Event cards follow the same rule, and an ended event shows no
  spots left.
- **Entry types:** `rsvp` (logged-in members, going / not going, up to the event's capacity; each answer shows a
  success toast with the server's message, "RSVP updated. You're going!" / "RSVP updated. You're not going."), `open`
  (a **Register** button that opens a name + email popover; a logged-in member's arrives filled in
  from the account, name editable, email locked. After registering, a **You're registered** dialog
  shows the event (cover, date, place) and Add to calendar links, and a **✓ Registered** badge replaces
  the button. A member's badge is a menu (like RSVP's) with **Not going**: a confirm, then status `not_going`
  (the cancellation email when on) and the Register button back; a visitor's registration is final
  and shows only until they reload) and `none` (no registration UI for anyone;
  admins can still add guests, who get the usual emails).
- **Registering again:** guests only answer going or not going; `cancelled` is set by an admin alone. A
  `not_going` row (member or visitor email) goes back to going with the usual confirmation, if the event has a
  seat left (a full event refuses it like a new registration). A visitor form, on the portal or the public page, can't
  bring back a row linked to an account: that member answers by account, on either. Any other row
  gets a warning dialog and nothing changes: "This email is already registered" (going, checked in), or
  "Your registration was cancelled" (`cancelled`, which the guest can't undo, by either answer).
- **Times:** lists, the feed card and the calendar show the site's clock (`start_at_site`), and
  the `from`/`to` filter means site days. The event page shows the event's own clock with its
  offset, plus the site's clock when that reads differently.
- **Guest count:** shown only for `rsvp` and `open` events whose **Show guest count** switch is on, and then
  to everyone, admins included. The switch alone decides; nobody gets an override. It counts
  `going` and `checked_in` guests.
- **Ended:** the event page says Ended once, in its status; the RSVP slot is dropped on an ended
  event (any entry type), and the guest count stays.
- **Meeting link:** an online `rsvp` or `open` event shows it to its registered guests (`going` or
  `checked_in`) and to site administrators; an online `none` event
  shows it to everyone who can see the post.
  An offline event never exposes one.
- **Registration:**
  - Members: logged in; `rsvp` and `open` events only (`open` accepts *going* only); refused
    once the event has ended.
  - Visitors (`open` only, `register-guest`): logged out, on a portal open to visitors. Guarded by
    a nonce bound to the event, a 5-per-minute IP rate limit and a hidden honeypot field; the
    email must be new to the event. The guest row has no `user_id`.
  - One guest row per member per event. A row an admin added under the member's email is
    claimed, not duplicated; an email already registered to another member is refused.
  - Saying *going* again keeps a checked-in guest checked in.
- **Visibility:** lists show published posts in discussion spaces the viewer can see (joined +
  public; public only for logged-out visitors on a publicly accessible portal), plus community-wide
  posts for anyone who can see the portal (not in a space's own list). Registration also
  accepts an unlisted post the viewer can open. Draft events and events with no post are not found.
  As in fcom, a post whose author's community profile isn't active (blocked, pending or
  deactivated) hides its event until the profile is active again. The admin still lists all of them.
- **Email:** a confirmation when a member becomes *going* and a cancellation when they move from
  *going* to *not going* (each toggled in Email Notifications, default on), plus the event
  reminders. "New event" notifications are fcom's own new-post notifications.
- **FluentEvent admin:** calendars and events can be created here too, with no space or post
  linked. An event created here has no post, so the portal doesn't show it, even in a space calendar.
  The fcom logo links out: on a space calendar's page to its space, on an event card to its post.

## Tests

- **Covered:** registration, entry types, the guest count and meeting-link rules
  (`tests/php/Integration/CommunityEndpointsTest.php`, `tests/php/Unit/Modules/CommunityEventFormatTest.php`);
  hook wiring and ordering (`tests/php/Unit/Hooks/HookRegistrationTest.php`); portal routes, card
  and composer filters (`tests/unit/fcom-portal/`); `SpaceHandler` — space edits leave the calendar
  alone, space deletion parks it (`tests/php/Integration/CommunityEventCreationTest.php`).
- **Not yet covered:** the `FeedHandler` post lifecycle and list visibility for
  private and secret spaces. Until they are, this document is the only record of that behaviour.

## Out of scope

Ticketed mode, waitlists, recurring events in posts, host notifications per registration,
schema.org markup.
