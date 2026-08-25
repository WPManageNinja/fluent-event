# FR-8: Calendars

**Summary:** A calendar groups events inside the space. The host/managers create one or more calendars; events are filed under them. Each calendar has a shareable public page that members can browse, plus its own settings and insights. (Event notifications go to that event's guests, FR-9 — there is no calendar-level subscribe/follow.)

**Builds on FR-6** (events reference a calendar), **FR-4** (settings + permissions). Logo/cover are plain image URLs in the `meta` JSON (`meta.logo_url` / `meta.cover_url`) — not columns, not WP attachments. Single-tenant: calendars are **space-owned**, managed by WP-user hosts/managers. Identity is WordPress users; there is no member/profile layer.

> **The calendar JSON column is `meta`, not `settings`.** It is deliberately open-ended —
> any per-calendar config lands here without a migration, and the model's `defaultMeta()`
> documents the keys in use. v1 writes exactly one, `theme_color`. `wp_fe_event_details`
> keeps its own column named `settings`; the two are unrelated and neither name is going to
> be unified.

> **v1 scope:** Multi-calendar (creating/managing/choosing calendars in the UI) is **deferred**. In v1 the plugin auto-creates a hidden **"Default Calendar"** in the background when the first event is created, and files every event under it — the calendar UI is not shown. Because `calendar_id` is always set, turning on multi-calendar later needs no migration. The rest of this spec describes the full (later) feature.

## Approach

- **New table** `wp_fe_calendars`. Per-calendar extra config in the calendar's `meta` column.
- **Space-owned.** Calendars belong to the space; only host/managers (capability `fe_manage_calendars`) create/manage them. Members browse.
- **Events link by `calendar_id`** (FR-6). One event → one calendar (or none = unfiled).
- **Shareable page** keyed by `slug`; lists that calendar's published events.
- **Logo/cover = direct image URLs in `meta` JSON** (`meta.logo_url` / `meta.cover_url`) — not columns, not WP attachments. They're view-only (never filtered or joined on), so per the queried→column / display-only→JSON rule they don't earn columns.

## Data model

**Table: `wp_fe_calendars`**

| Column           | Type        | Notes                                            |
| ---------------- | ----------- | ------------------------------------------------ |
| `id`             | BIGINT PK   |                                                  |
| `title`          | VARCHAR     | calendar name                                    |
| `slug`           | VARCHAR     | unique; the shareable URL                        |
| `description`    | TEXT NULL   |                                                  |
| `status`         | VARCHAR     | `active` \| `draft`; default `active`             |
| `meta`           | JSON NULL   | open-ended. v1 stores `theme_color`. Planned: `logo_url`, `cover_url`, who-can-manage (caps), notification prefs |
| `created_by`     | BIGINT      | host/manager user id                             |
| `created_at`     | TIMESTAMP   |                                                  |
| `updated_at`     | TIMESTAMP   |                                                  |

Indexes: **`PRIMARY(id)` + `UNIQUE(slug)` only.** No `status` index — this table holds
~10–100 rows ever (1–2 pages), so a full scan beats index descent + back-to-table random
reads, and the column is two-valued (near-zero selectivity); the optimizer would skip it
anyway. `UNIQUE(slug)` is kept for **correctness** (no duplicate slugs) — the fast
`/calendar/{slug}` lookup is a side effect. Revisit only if the table ever grows large.

## Functional requirements

### FR-8.1 — Create & manage calendars

- Host/managers create calendars (name, slug, description, logo/cover). Multiple per space. (unfiltered cal #1, #2)

### FR-8.2 — Assign events to calendars

- An event is filed under a calendar via `calendar_id`; events can be organized/split across several calendars. (cal #3, #5)

### FR-8.3 — Shareable calendar page

- Each calendar has a public page at its `slug` listing its published events; members browse it. (cal #4)
- Respects `status` — an `active` calendar is listed and reachable; a `draft` one is not published.

### FR-8.5 — Member event view: joined vs browse

- Members see events they've joined vs all discoverable events on a calendar; host/managers see the full management view. (cal #6)

### FR-8.6 — Calendar guest/member list

- Host/managers see members/guests attached to a calendar (people who joined its events). (cal #7)

### FR-8.7 — Per-calendar settings

- Theme and who-can-manage (capability) configured per calendar, stored in `meta`. (cal #9)

### FR-8.8 — Calendar insights

- Per-calendar analytics (page views, event conversions); manager capability. (cal #8)

## Acceptance criteria

- A manager creates a calendar; it gets a unique slug, and a public page once it is `active`.
- Filing an event under a calendar makes it appear on that calendar's page.
- A `draft` calendar's page is not publicly listed/accessible.
- Per-calendar theme changes apply only to that calendar.

## Open questions

→ Tracked in [clarifying-questions.md](../../clarifying-questions.md).
