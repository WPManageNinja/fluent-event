# FR-8: Calendars

**Summary:** A calendar groups events inside the space. The host/managers create one or more calendars; events are filed under them. Each calendar has a shareable public page that members can browse and follow, plus its own settings, insights, and notifications.

**Builds on FR-6** (events reference a calendar), **FR-1/FR-3** (identity, members), **FR-4** (settings + permissions), **FR-7** (logo/cover media). Single-tenant: calendars are **space-owned**, not per-user.

> **v1 scope:** Multi-calendar (creating/managing/choosing calendars in the UI) is **deferred**. In v1 the plugin auto-creates a hidden **"Default Calendar"** in the background when the first event is created, and files every event under it — the calendar UI is not shown. Because `calendar_id` is always set, turning on multi-calendar later needs no migration. The rest of this spec describes the full (later) feature.

## Approach

- **New tables** `wp_fevents_calendars` + `wp_fevents_calendar_followers`. Per-calendar extra config in the calendar's `settings` column.
- **Space-owned.** Calendars belong to the space; only host/managers (capability `fevents_manage_calendars`) create/manage them. Members browse + follow.
- **Events link by `calendar_id`** (FR-6). One event → one calendar (or none = unfiled).
- **Shareable page** keyed by `slug`; lists that calendar's published events.
- **Media via FR-7** for logo/cover.

## Data model

**Table: `wp_fevents_calendars`**

| Column           | Type        | Notes                                            |
| ---------------- | ----------- | ------------------------------------------------ |
| `id`             | BIGINT PK   |                                                  |
| `name`           | VARCHAR     |                                                  |
| `slug`           | VARCHAR     | unique; the shareable URL                        |
| `description`    | TEXT NULL   |                                                  |
| `logo_media_id`  | BIGINT NULL | FK → `wp_fevents_media.id` (FR-7)                |
| `cover_media_id` | BIGINT NULL | FK → `wp_fevents_media.id` (FR-7)                |
| `visibility`     | VARCHAR     | `public` \| `unlisted` \| `private`              |
| `settings`       | JSON NULL   | theme, who-can-manage (caps), notification prefs |
| `created_by`     | BIGINT      | host/manager user id                             |
| `created_at`     | TIMESTAMP   |                                                  |
| `updated_at`     | TIMESTAMP   |                                                  |

Indexes: `(slug)`, `(visibility)`.

**Table: `wp_fevents_calendar_followers`** — members following a calendar.

| Column        | Type      | Notes         |
| ------------- | --------- | ------------- |
| `id`          | BIGINT PK |               |
| `calendar_id` | BIGINT    | FK → calendar |
| `user_id`     | BIGINT    | the follower  |
| `created_at`  | TIMESTAMP |               |

Index: `(calendar_id, user_id)` unique.

## Functional requirements

### FR-8.1 — Create & manage calendars

- Host/managers create calendars (name, slug, description, logo/cover). Multiple per space. (unfiltered cal #1, #2)

### FR-8.2 — Assign events to calendars

- An event is filed under a calendar via `calendar_id`; events can be organized/split across several calendars. (cal #3, #5)

### FR-8.3 — Shareable calendar page

- Each calendar has a public page at its `slug` listing its published events; members browse and can follow it. (cal #4)
- Respects `visibility` (`public` listed, `unlisted` link-only, `private` invite/manager-only).

### FR-8.4 — Follow a calendar

- A member follows a calendar (row in `calendar_followers`) → opts into its notifications. (cal #4)

### FR-8.5 — Member event view: joined vs browse

- Members see events they've joined vs all discoverable events on a calendar; host/managers see the full management view. (cal #6)

### FR-8.6 — Calendar guest/member list

- Host/managers see members/guests attached to a calendar (followers + people who joined its events). (cal #7)

### FR-8.7 — Per-calendar settings

- Theme, visibility, and who-can-manage (capability) configured per calendar, stored in `settings`. (cal #9)

### FR-8.8 — Calendar insights

- Per-calendar analytics (page views, follows, event conversions); manager capability. (cal #8)

### FR-8.9 — Calendar notifications

- Notify followers of new / updated / upcoming events on a calendar (background job + email FR-4.7). (cal #10)

## Acceptance criteria

- A manager creates a calendar; it gets a unique slug + public page per its visibility.
- Filing an event under a calendar makes it appear on that calendar's page.
- A member follows a calendar and receives its notifications; unfollowing stops them.
- A `private` calendar's page is not publicly listed/accessible.
- Per-calendar theme/visibility changes apply only to that calendar.

## Open questions

→ Tracked in [clarifying-questions.md](../../clarifying-questions.md).
