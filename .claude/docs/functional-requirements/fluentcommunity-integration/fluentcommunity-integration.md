# FR-10: FluentCommunity Integration

**Summary:** Connect Fluent Events with FluentCommunity (fcom). This is optional. Fluent Events still works on its own. When fcom is installed, a new space type called `event` shows up. Each event space is tied to one Fluent Events calendar. The events themselves always live in the `wp_fe_*` tables — fcom never stores event data. When both plugins are on, fcom owns the user identity.

**Depends on:** FR-6 (events), FR-8 (calendars), FR-9 (notifications). Identity is WordPress users; fcom adds its XProfile for community data, both keyed off `wp_users.ID`.

## How it works

- A **bridge module** inside Fluent Events (`Modules/FluentCommunity/`) turns on only when fcom is active. Without fcom, nothing changes.
- fcom already supports space types (`community`, `course`, `sidebar_link`) in one table using a `type` column. We add one more: `event`. No change to fcom's database.
- The link to the calendar is saved in the space's existing `settings` JSON. Again, no new fcom table or column.
- All event and calendar data stays in `wp_fe_*`. fcom only shows the space and renders the events UI.

## Functional requirements

### FR-10.1 — Turn on only with fcom

The bridge loads only if fcom is detected (`class_exists` / version check). No fcom → Fluent Events runs standalone, exactly as FR-4…FR-9.

### FR-10.2 — The `event` space type

- Add a model `EventSpace` with `type = 'event'` (same pattern fcom uses for `course`).
- Store the link in `space.settings`: `calendar_id` plus a flag `is_event_space = true`.
- The space page shows the Fluent Events calendar/event UI. Add a Calendar/Events tab using the `fluent_community/space_header_links` hook.

### FR-10.3 — One space, one calendar

- **Create an event space** → the bridge creates a Fluent Events calendar with the same title and saves its `calendar_id` on the space. One space = one calendar.
- That calendar is a normal Fluent Events calendar. It also shows in the standalone events portal (which supports many calendars).
- **Titles stay in sync.** Rename the space → the calendar renames, and the other way around.
- The link starts from fcom. A calendar made directly in the events portal has no space (it's just a calendar).
- **Deleting a space:** see [clarifying-questions.md](../clarifying-questions.md) (Q7).

### FR-10.4 — Create and show events on both sides

- **In the fcom space:** "Create event" saves a `wp_fe_event_details` row with this calendar's `calendar_id`.
- **In the events portal:** an event added to that calendar shows up in the fcom space too. The space loads events by `calendar_id`, so there's one source and no duplication.

### FR-10.5 — Identity and audience

- Identity is always **WordPress users** (`wp_users`). With fcom on, fcom's XProfile / space members supply community identity data, keyed off `wp_users.ID`. Fluent Events has no profile table of its own in either mode (attendees are anonymous email guests; hosts/managers are WP users).
- For a linked calendar, event notifications also reach the **space's members** (FR-10.6). There is no calendar follow/subscribe concept — standalone calendars have no subscriber list, so their event notifications reach the event's guests only (FR-9).
- To confirm with the team lead: see [clarifying-questions.md](../clarifying-questions.md) (Q8).

### FR-10.6 — Notifications

- Notifications follow FR-9. Recipients are the **space members**.
- **Check the link on every fire.** When a notification fires for an event (or a calendar), look at the event's parent calendar. If it's fcom-linked (`settings.fcom_space_id` is set), also deliver the notification into fcom's notification system so it shows in fcom's bell.
- Email is sent once either way (FR-9).
- **Open:** deliver to both Fluent Events and fcom, or only one (so a member isn't notified twice)? See [clarifying-questions.md](../clarifying-questions.md) (Q9).

### FR-10.7 — Post to the space feed (optional)

- A per-space toggle: `settings.announce_in_feed` (default **off**).
- When on, publishing an event also posts an announcement to the space feed (`fcom_posts`), so it shows in the community timeline. Off → events show only in the calendar/event view.
- **Open:** is this control set on the space (applies to all its events) or chosen per event at creation time? See [clarifying-questions.md](../clarifying-questions.md) (Q10).

## Storage

- **fcom side:** no schema change. A `fcom_spaces` row with `type='event'` and `settings.calendar_id`, `settings.is_event_space`, `settings.announce_in_feed`.
- **Fluent Events side:** the linked calendar is a normal `wp_fe_calendars` row. Save its origin (e.g. `settings.fcom_space_id`) so we can look it up and keep it in sync. Events use `calendar_id` as usual.

## fcom hooks used (no edits to fcom core)

- `fluent_community/space/created` — create and link the calendar.
- `fluent_community/space/updated` — sync the title.
- `fluent_community/space/before_delete` / `space/deleted` — handle the link (see Q7).
- `fluent_community/spaces_api_response` / `space_api_response` — add `calendar_id` + event flag to the space data.
- `fluent_community/space_header_links` — add the Calendar/Events tab.
- `fluent_community/feeds_query` — scope announcement posts (only if announcing).

## Acceptance criteria

- fcom off → Fluent Events works exactly as FR-4…FR-9. The bridge does nothing.
- Create an event space → one linked calendar is created, shows in the events portal too, and titles stay in sync.
- An event created in the fcom space and one created in the events portal (same calendar) both show on both sides.
- With fcom on, identity comes from fcom, and a linked calendar's notification audience is the space's members.
- Feed announcement is off by default; when on, publishing an event posts to the space feed.
- No fcom database migration.

## Open questions

→ What happens to the calendar when an event space is deleted — [clarifying-questions.md](../clarifying-questions.md) (Q7).
