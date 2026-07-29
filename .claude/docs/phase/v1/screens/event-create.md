# Screen — Create event

> Part of the [v1 build plan](../v1_docs.md). Conventions: [README.md](README.md).

**Route** `#/events/new`, optionally `?calendar_id=<id>` · **Nav** none · **Phase** 3 ·
**Module** `resources/admin/modules/events/`

---

## Purpose

Creates an event under a calendar. Every event needs one — `event_details.calendar_id` is
NOT NULL — so the whole screen is shaped around making sure a calendar is chosen.

## Entry points

Three, and they differ only in whether a calendar is already known:

| From | Route | Calendar field |
|---|---|---|
| **Add Event** on a calendar detail screen | `#/events/new?calendar_id=5` | preselected to that calendar |
| **Add Event** on the combined Events screen | `#/events/new` | empty, user must choose |
| **Add New Event** in the wp-admin sidebar | `#/events/new` | empty, user must choose |

The calendar arrives as a **query parameter, not a path segment**. The event does not belong
to a URL hierarchy — it belongs to whichever calendar the form says — and a query param
makes it plainly optional, which a path segment would not.

## Calendar preselection

- **With `calendar_id`** — the select is preselected and the user sees which calendar they
  are adding to. **Leave it editable.** They may have navigated from the wrong calendar, and
  locking the field only forces them to start over.
- **Without it** — the select is empty and required. Nothing else on the form matters until
  it is answered, so it belongs first.
- **With no calendars existing at all** — do not render a form that cannot submit. Route to
  [calendar-create.md](calendar-create.md) with an explanation. This is reachable on a fresh
  install via the sidebar's Add New Event.

> **`calendar_id` is user input.** It comes from a URL anyone can edit. The server must
> confirm the calendar exists and that the user may write to it — never trust the parameter
> to name a calendar they are allowed to touch. See [routes.md](../../../../rules/routes.md).

## Fields

| Field | Stored in | Required | Notes |
|---|---|---|---|
| Calendar | `event_details.calendar_id` | yes | Preselected from the query param when present |
| Title | `wp_posts.post_title` | yes | |
| Description | `wp_posts.post_content` | no | |
| Start | `event_details.start_at` | yes | |
| End | `event_details.end_at` | yes | Must be after start |
| Timezone | `event_details.timezone` | defaulted | Seeded from the site timezone |
| Location type | `event_details.venue_type` | defaulted | `online` or `offline`. `hybrid` exists in the schema but v1 does not use it |
| Meeting URL | `meta.online_meeting_url` | conditional | Shown when location type is `online` |
| Address | `meta.address` | conditional | Shown when location type is `offline` |

Not on this screen:

- **Cover image** (`meta.cover_url`) belongs to the event's edit screen, for the same reason
  as calendars: a media picker in the create flow slows down the common case.
- **`status`** defaults to `public`, with `post_status` kept in step.
- **No venue, organizer or performer taxonomy fields** — v1 does not write to them. Location
  is `venue_type` plus the URL or address in `meta`. See
  [event-detail.md](event-detail.md).

## Validation

```php
$this->validate($data, [
    'calendar_id' => 'required|exists:fe_calendars,id',
    'title'       => 'required|string',
    'start_at'    => 'required|date',
    'end_at'      => 'required|date|after:start_at',
    'venue_type'  => 'required|in:online,offline',
]);
```

**`end_at` after `start_at` is the one rule that will actually fire in practice** — the
schema has both NOT NULL but nothing stops an end before a start.

The meeting URL is user-entered and later rendered as a link, so filter the protocol to
`https?:` on the way in and again at render. See [vue.md](../../../../rules/vue.md).

## The write is one transaction

Creating an event writes a `wp_posts` row **and** an `event_details` row. They must land
together — a post with no details row is an event the plugin cannot read, and it will not be
visible to clean up.

`DB::transaction()` at the service layer, per
[patterns/backend.md](../../../patterns/backend.md). Do not catch inside the closure; that
commits half the work.

## On success

Redirect to the new event's detail screen, `#/events/:id` —
[event-detail.md](event-detail.md).

## Permissions

Requires `manage_all_events`, or ownership of the target calendar via `manage_own_calendars`. A user without it sees no
Add Event button anywhere, gets no sidebar entry, and is refused by the policy if they reach
the route directly.

## Rules

- Extract fields explicitly; never `Event::create($request->all())`.
- Disable submit while in flight — a double-click here creates two events *and* two posts.
- Surface failures. A silent catch after a partial write is the worst case on this screen.
- Every label and message through `$t()`.
