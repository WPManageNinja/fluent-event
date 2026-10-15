# FluentEvent — Functional Requirements

> Index of functional requirements. Each feature is specified in its own file in this folder. This file keeps summaries only — open the linked part for the full spec.

---

## Identity model (no custom auth)

**FluentEvent has no custom authentication, profile, or member-management layer.** Event configuration happens in **wp-admin**, performed by WordPress users (hosts / managers, gated by capability). Public event pages are **open for email-only entry** — an attendee is captured as a `wp_fe_guests` row (`name` + `email`, no `user_id` link), never a plugin-owned account. No front-end login, profile area, OTP, forgot-password, or member moderation.

> **Removed in pivot:** former **FR-1 Authentication**, **FR-2 User Profile & Account Management**, **FR-3 Member Management**, and **FR-5 Activity Tracking (Audit Log)**. Numbering keeps the gaps so existing cross-references stay valid.

## FR-4: Settings & Configuration

**Summary:** Central settings area (fcom-style) where the host configures general settings, customization, feature addons, managers, access management, privacy, and customizable email templates. Two-store split: bootstrap `general` + db_version in `wp_options` (autoloaded, pre-ORM); all other sections + per-user meta in a generic `wp_fe_meta` table (one row per section).

→ Full spec: [settings.md](settings.md)

## FR-6: Events (core)

**Summary:** The core event entity + lifecycle — create, configure, publish, manage events of any kind; visitors register anonymously. **Event = CPT hybrid**: identity in `wp_posts` (`post_type=fe_event`), config in `wp_postmeta`, typed relational data in `wp_fe_event_details` (1:1 `post_id`). **Classification via WP taxonomies**: `fe_event_type`, `fe_venue`, `fe_organizer`, `fe_performer` (reusable across events, term meta for details). **Ticketing model**: event → `ticket_types` (tiers) → `guests` (one row per ticket; a purchase = guest rows sharing `group_id`, the `is_buyer=1` row carries order + payment — no separate registrations table). Online/offline/hybrid, free/paid, open/approval, per-tier capacity, dynamic per-attendee forms, check-in. Performers = thin taxonomy (name + photo). Sponsors = deferred. No account — keyed by email. → [db/fe_db_schema.sql](../db/fe_db_schema.sql).

→ Full spec: [events.md](events.md)

## FR-7: Media (WordPress media library)

**Summary:** All imagery — event covers/galleries, calendar logos/covers, email branding — uses the **WordPress media library**. No custom media table and no image columns — the resolved image **URL** is stored in the owning record's JSON config column — `event_details.settings.cover_url`, `calendars.meta.logo_url`/`.cover_url` (the two columns are named differently; `calendars.meta` is the open-ended one). WP handles storage, sizes (thumbnails/`srcset`), alt text, and s3/CDN offload (via WP Offload Media). Low volume makes a custom table not worth it.

→ Full spec: [media-archive.md](media-archive.md)

## FR-8: Calendars

**Summary:** Space-owned calendars that group events. Host/managers create calendars; events file under them via `calendar_id`. Each has a shareable public page members browse, plus per-calendar settings and insights.

→ Full spec: [calendar.md](calendar.md)

## FR-9: Notifications

**Summary:** Notification system across channels — in-app feed for logged-in WP users (hosts/managers, with unread state) + email (FR-4.7). Account-less event guests are reached by **email only**. Per-recipient rows, typed action registry, polymorphic object, render-at-read, per-user per-channel preferences. The dispatcher fans a trigger out to the right recipients on their enabled channels.

→ Full spec: [notifications.md](notifications.md)

## FR-10: FluentCommunity Integration

**Summary:** Optional bridge into FluentCommunity. With fcom active, an event is a post (`content_type = 'event'`, `meta.event_id`) in a discussion space, and each space links to one `private` FluentEvent calendar. Event data always lives in `wp_fe_*`; fcom stores only the post. Without fcom, FluentEvent runs standalone.

→ No separate spec: the code in `Modules/FluentCommunity/` and its tests are the record.
