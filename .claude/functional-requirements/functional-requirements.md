# Fluent Events — Functional Requirements

> Index of functional requirements. Each feature is specified in its own file under [`functional-requirements/`](functional-requirements/). This file keeps summaries only — open the linked part for the full spec.

---

## FR-1: Authentication

**Summary:** Visitors log in or sign up directly from any event. Auth runs on WordPress core; identity is the WordPress `users` table. A plugin-owned `wp_fevents_profiles` table holds everything else (1:1 per user). Joinees with no account are auto-registered and confirmed by email code. Covers signup, login, verify-by-code, forgot-password, host-accept auto-login, invitation links, and profile provisioning/lifecycle.

→ Full spec: [functional-requirements/authentication/authentication.md](functional-requirements/authentication/authentication.md)

## FR-2: User Profile & Account Management

**Summary:** After login, users edit their profile details, change password and email (both OTP-gated), and deactivate or delete their account.

→ Full spec: [functional-requirements/authentication/user-profile-management.md](functional-requirements/authentication/user-profile-management.md)

## FR-3: Member Management

**Summary:** Users with member-edit permission view and manage the member list (from profiles) — filterable by status tabs, card/table views, sortable. Managers update member status; blocked/suspended members are locked out of the portal with a warning.

→ Full spec: [functional-requirements/member-management/member-management.md](functional-requirements/member-management/member-management.md)

## FR-4: Settings & Configuration

**Summary:** Central settings area (fcom-style) where the host configures general settings, customization, feature addons, managers, access management, privacy, and customizable email templates. Two-store split: bootstrap `general` + db_version in `wp_options` (autoloaded, pre-ORM); all other sections + per-user meta in a generic `wp_fevents_meta` table (one row per section).

→ Full spec: [functional-requirements/settings/settings.md](functional-requirements/settings/settings.md)

## FR-5: Activity Tracking (Audit Log)

**Summary:** Append-only activity log — who did what to which object, with structured context — for audit, debugging, and activity views. Typed action registry, polymorphic typed object, actor/object split, context JSON, audit fields (ip/UA).

→ Full spec: [functional-requirements/activity-tracking/activity-tracking.md](functional-requirements/activity-tracking/activity-tracking.md)

## FR-6: Events (core)

**Summary:** The core event entity + lifecycle — create, configure, publish, manage events; members discover, view, and join (free/paid, approval, capacity). Covers content, location + map, online meetings, invites/guests/import, visibility, custom forms, insights, embed, clone, delete, reminders. Attend/auth flows reuse FR-1.

→ Full spec: [functional-requirements/events/events.md](functional-requirements/events/events.md)

## FR-7: Media Archive

**Summary:** One shared `wp_fevents_media` table tracking every upload — event covers/galleries, calendar images, avatars, email branding. Polymorphic (object_type/id), driver-aware (local/s3), with upload→attach flow and orphan cleanup (status: active/deleted).

→ Full spec: [functional-requirements/media-archive/media-archive.md](functional-requirements/media-archive/media-archive.md)

## FR-8: Calendars

**Summary:** Space-owned calendars that group events. Host/managers create calendars; events file under them via `calendar_id`. Each has a shareable public page members browse + follow, plus per-calendar settings, insights, and notifications.

→ Full spec: [functional-requirements/calendar/calendar.md](functional-requirements/calendar/calendar.md)
