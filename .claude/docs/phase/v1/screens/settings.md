# Screen — Settings

> Part of the [v1 build plan](../v1_docs.md). Conventions: [README.md](README.md).

**Route** `#/settings` · **Nav** secondary (right side of the topbar) · **Phase** 2 ·
**Module** `resources/admin/modules/settings/`

---

## Purpose

Four settings. [FR-4](../../../functional-requirements/settings/settings.md) specifies
seven sections, but five of them configure features v1 does not have — a settings page
mostly full of controls for absent functionality is worse than a small one.

## What v1 stores

| Setting | Type | Effect |
|---|---|---|
| **Default events view** | `timeline` \| `calendar` | which view the events screens open on |
| **Default date format** | WP format string | how the plugin renders dates on its own surfaces |
| **Default time format** | WP format string | likewise for times |
| **Enable dark mode** | boolean | whether dark mode exists at all. Off ⇒ light only |

## Roles section

**Global roles only in v1** — no per-calendar or per-event managers. Model:
[permissions.md](../permissions.md).

The section lists users holding a plugin role and lets an admin promote or revoke. That is
the whole UI: a user picker, a role select, a list, a remove action.

Two roles in v1, plus WordPress administrators who bypass the system entirely:

| Role | May |
|---|---|
| `fevent_manager` | everything, including settings |
| `fevent_viewer` | **view** calendars, events and guests — no creating, editing, deleting, no settings |

Points that matter when building it:

- **Only `settings/manage` may open this screen.** A manager who could grant themselves
  settings access defeats the split.
- **WordPress administrators bypass the whole system** and must not appear as removable.
  Revoking one here would be a footgun with no undo.
- **Promoting is one write** — `update_user_meta($id, '_fevent_role', 'fevent_manager')`.
  Revoking is one `delete_user_meta`. No capability is granted; `wp_capabilities` is never
  touched.
- **Validate the role string against `getAllRoles()`.** This endpoint is the
  privilege-escalation route in the plugin — never persist whatever the request sent.
- **Nothing runs on activation or on load.** Permissions are granted per user, on demand.
  There is no bootstrap step — see [permissions.md](../permissions.md).

## Enable dark mode is a feature gate, not a default

With it off there is no dark theme to pick, so
[ThemeSwitcher.vue](../../../../../resources/admin/components/ThemeSwitcher.vue) should not
render at all — a toggle that does nothing is worse than an absent one.

Two things to get right:

- A user who previously chose dark has it stored in `localStorage`. **Read the gate before
  the stored preference**, or that user is stranded in a theme the site no longer supports.
- Light/dark stays a **per-user** choice while the gate is on. This setting decides whether
  the choice exists, never what it defaults to.

## Default events view interacts with localStorage

The view switch already persists per user. Precedence must be explicit:

1. Stored per-user choice wins.
2. This setting seeds users who have no stored choice.

Otherwise the setting appears to do nothing for anyone who has ever touched the toggle.

## The date and time formats are ours, deliberately

They are **frontend** formats — separate from `get_option('date_format')` /
`get_option('time_format')`, which govern wp-admin.

- Seed the defaults from the WP values so a fresh install matches the site rather than
  showing something arbitrary, then let them diverge.
- Store WP-style format strings (`F j, Y`, `g:i a`).
- **Show a live preview** of the rendered result. A raw format string is unreadable to most
  hosts.

## Read from WordPress, don't re-ask

- **Week starts on** — `get_option('start_of_week')`. The calendar grid needs it; WP
  already knows.
- **Site timezone** — read the site setting; v1 stores no override. Note
  `wp_timezone_string()` is WP 5.3+ while
  [fluent-event.php](../../../../../fluent-event.php) declares `Requires at least: 5.2`, so
  either raise the floor or fall back to `get_option('timezone_string')` plus a UTC-offset
  path.

## Storage

Per FR-4's two-store split, honoured from the start:

- All four are UI preferences → **`wp_fe_meta`**, one `customization` row:
  `object_type = 'option'`, `meta_key = 'customization'`. Nothing in v1 is read early
  enough to need autoloading.
- The **one row per section** rule holds as sections are added: editing one must never
  rewrite another, or two admins saving different tabs clobber each other.
- `FEVENT_DB_VERSION` stays in `wp_options` — the migrator reads it before the tables are
  guaranteed to exist. `fevent_general` is not needed until there is a general section.

## Not settings

- **Brand name and logo** are static plugin assets. The topbar's hardcoded `beta` span is a
  code fix, not a settings-driven one.
- **Event defaults, RSVP behaviour, uninstall data removal** — considered and dropped for
  v1. Revisit when there is more product for them to configure.

## Rules

Settings are the classic mass-assignment sink. Extract and validate each field explicitly;
never persist a raw request payload. See
[controllers-services.md](../../../../rules/controllers-services.md) and
[always.md](../../../../rules/always.md).
