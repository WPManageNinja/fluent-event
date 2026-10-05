# FR-10: FluentCommunity Integration

**Summary:** With FluentCommunity (fcom) active, an event is a **post** in a discussion space.
The post carries `content_type = 'event'` and `meta.event_id`; everything about the event lives in
FluentEvent's tables. FluentEvent pushes all of it into fcom through hooks; fcom only gains a few
event-agnostic extension points. Without fcom, FluentEvent runs standalone and nothing below loads.

Source spec: [../integrations/fcom.md](../integrations/fcom.md). There is no `event` space type any more.

## Ownership

| Concern | Owner | Where |
|---|---|---|
| Post title, description, comments, reactions, scope, schedule | fcom | `fcom_posts` (`content_type = 'event'`, `meta.event_id`) |
| Event title, description, dates, venue, cover, entry type, capacity | FluentEvent | `wp_posts` (`fe_event`) + `fe_event_details.meta` |
| Space ↔ calendar link | FluentEvent | `fe_calendars.meta.fcom_space_id` |
| Event → post link | FluentEvent | `fe_event_details.meta.fcom_feed_id` |
| Guests | FluentEvent | `fe_guests` (`source = 'community'`, `source_id` = post id) |

Post and event titles/descriptions are separate. Editing the post uses fcom's normal edit modal.
The event card shows an **Edit event** button to `write_events` holders, which opens the FluentEvent
edit page.

## Bridge (FluentEvent `Modules/FluentCommunity/`)

Registered on `fluent_community/portal_loaded`, so it only runs while fcom is active.

- **PortalHandler** — top-level **Events** nav (`fluent_community/main_menu_items`, `mobile_menu`,
  `app_route_paths`), the space **Events** tab (`space_header_links`, discussion spaces only), and
  the portal script printed at `fluent_community/before_js_loaded`.
- **FeedHandler** — the event-post lifecycle:
  - `new_feed_data_type_event` — `write_events` check, discussion-space check, event validation,
    media stripped. Vetoes return `WP_Error` (a thrown exception becomes a bare HTTP 500 in fcom).
  - `just_created_type_event` — find or create the space calendar, create the event, write
    `meta.event_id`. If the event can't be created, the post is deleted.
  - `update_feed_data_type_event` / `update_data` — keep the event link through edits (fcom
    rebuilds post meta), block moves to the profile or to non-discussion spaces.
  - `feed/updated` — a post moved to another space moves its event to that space's calendar.
  - `feed/before_deleted` — deleting the post deletes the event and its guests.
  - `feed_general_config` + `rendering_feed_model` — batch-load events and attach the portal
    payload as `feed.fluent_event`.
- **SpaceHandler** — `space/before_delete` deletes the space calendar with its events and guests
  (fcom bulk-deletes the posts without per-post hooks); `space/updated` keeps the calendar's title
  and slug in step with the space.

## Portal UI (FluentEvent `resources/portal/`)

- `fe-portal.js` registers routes through fcom's `fluent_com_portal_routes`: `/events`,
  `/space/:space/events` and `/space/:space/create-event`. It also registers the event card for
  `content_type = 'event'`, the "Create new event" entry in the space "…" menu, and switches the
  post editor's attachments off for event posts.
- The pages are a separate Vue + Element Plus app loaded on first visit (fcom exposes neither
  to add-ons). The card is a string template on fcom's own Vue.
- REST: `fluent-event/v2/community/*` — event lists, one event, register, cancel, cover upload.

## fcom extension points (event-agnostic)

| JS filter | Where | Used for |
|---|---|---|
| `fluent_com_feed_content_components` | `Feed.vue`, `RenderChildPost.vue` | Render a post body by `content_type` |
| `fluent_com_space_actions` | `_SpaceMenu.vue` | Extra items in the space "…" menu |
| `fluent_com_composer_config` | `CreateStatusForm.vue` (+ `mediaApp`, `giphyApp` flags) | Turn composer apps off per post type |

## Rules

- **Who creates events:** FluentEvent staff with `write_events` who may also post in the space.
- **Calendars:** created lazily on the first event, named and slugged after the space, status
  `private` (no public page). Admins may change the status later.
- **Statuses:** events and calendars are `public | private | draft`. fcom-created events start
  `public`. fcom renders `public` and `private` events; `draft` events are not returned.
- **Entry types:** `rsvp` (logged-in, going / not going), `open` (anyone who can see the post;
  name + email, user linked when logged in, one registration per email), `none`.
- **Capacity:** `max_guests` (`-1` = unlimited) counts `going` + `checked_in`; the event row is
  locked while a seat is taken, so the last seat can't be taken twice. Full closes registration.
- **Show guest count:** count only, default on. Staff always see it.
- **Meeting link:** only for registered guests and staff; anonymous registrants get it in the
  response and the confirmation email.
- **Visibility:** lists and registration follow the post — published posts in discussion spaces the
  viewer can see (joined + public; public only for logged-out visitors on a public portal).
- **Email:** registration confirmation (toggle in Email Notifications, default on) plus the
  existing reminders. "New event" notifications are fcom's own new-post notifications.
- **FluentEvent admin:** no "Add New Event" menu, no create-event or create-calendar buttons,
  no `/events/new` or `/calendars/new` routes. Editing and deleting stay. The create forms
  (`CreateOrUpdateEvent`, `CreateOrUpdateCalendar`) and REST create endpoints stay in the code.

## Out of scope

Ticketed mode, waitlists, recurring events in posts, host notifications per registration,
schema.org markup.
