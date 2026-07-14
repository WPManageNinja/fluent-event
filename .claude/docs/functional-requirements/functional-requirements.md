# Fluent Events — Functional Requirements

> Index of functional requirements. Each feature is specified in its own file under [`functional-requirements/`](functional-requirements/). This file keeps summaries only — open the linked part for the full spec.

---

## Identity model (no custom auth)

**Fluent Events has no custom authentication, profile, or member-management layer.** Event configuration happens in **wp-admin**, performed by WordPress users (hosts / managers, gated by capability). Public event pages are **open for email-only entry** — an attendee is captured as a `wp_fevents_guests` row (`name` + `email`, no `user_id` link), never a plugin-owned account. No front-end login, profile area, OTP, forgot-password, or member moderation.

> **Removed in pivot:** former **FR-1 Authentication**, **FR-2 User Profile & Account Management**, **FR-3 Member Management**, and **FR-5 Activity Tracking (Audit Log)**. Numbering keeps the gaps so existing cross-references stay valid.

## FR-4: Settings & Configuration

**Summary:** Central settings area (fcom-style) where the host configures general settings, customization, feature addons, managers, access management, privacy, and customizable email templates. Two-store split: bootstrap `general` + db_version in `wp_options` (autoloaded, pre-ORM); all other sections + per-user meta in a generic `wp_fevents_meta` table (one row per section).

→ Full spec: [functional-requirements/settings/settings.md](functional-requirements/settings/settings.md)

## FR-6: Events (core)

**Summary:** The core event entity + lifecycle — create, configure, publish, manage events of any kind; visitors register anonymously. **Ticketing model**: event → `ticket_types` (tiers) → `registrations` (buyer + payment, N slots) → `guests` (one per slot). Online/offline/hybrid, free/paid, open/approval, per-tier capacity, dynamic per-attendee forms, check-in. No account — keyed by email.

→ Full spec: [functional-requirements/events/events.md](functional-requirements/events/events.md)

## FR-7: Media (WordPress media library)

**Summary:** All imagery — event covers/galleries, calendar logos/covers, email branding — uses the **WordPress media library**. No custom media table; image columns store a `wp_posts` attachment ID. WP handles storage, sizes (thumbnails/`srcset`), alt text, and s3/CDN offload (via WP Offload Media). Low volume makes a custom table not worth it.

→ Full spec: [functional-requirements/media-archive/media-archive.md](functional-requirements/media-archive/media-archive.md)

## FR-8: Calendars

**Summary:** Space-owned calendars that group events. Host/managers create calendars; events file under them via `calendar_id`. Each has a shareable public page members browse, plus per-calendar settings and insights.

→ Full spec: [functional-requirements/calendar/calendar.md](functional-requirements/calendar/calendar.md)

## FR-9: Notifications

**Summary:** Notification system across channels — in-app feed for logged-in WP users (hosts/managers, with unread state) + email (FR-4.7). Account-less event guests are reached by **email only**. Per-recipient rows, typed action registry, polymorphic object, render-at-read, per-user per-channel preferences. The dispatcher fans a trigger out to the right recipients on their enabled channels.

→ Full spec: [functional-requirements/notifications/notifications.md](functional-requirements/notifications/notifications.md)

## FR-10: FluentCommunity Integration

**Summary:** Optional bridge that deep-integrates Fluent Events into FluentCommunity. When fcom is active, registers a new `event` space type whose space is 1:1-bound to a Fluent Events calendar (auto-created, title-synced, also shown in the standalone portal). Events always live in `wp_fevents_*` (fcom stores none); in integrated mode fcom is the canonical identity.

→ Full spec: [functional-requirements/fluentcommunity-integration/fluentcommunity-integration.md](functional-requirements/fluentcommunity-integration/fluentcommunity-integration.md)
