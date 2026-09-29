# Screen — Settings

> Part of the [v1 build plan](../v1_docs.md). Conventions: [README.md](README.md).

**Route** `#/settings` · **Nav** secondary (right side of the topbar) · **Phase** 2 ·
**Module** `resources/admin/modules/settings/`

---

## Purpose

Few settings. [FR-4](../../../functional-requirements/settings/settings.md) specifies
seven sections, but five of them configure features v1 does not have — a settings page
mostly full of controls for absent functionality is worse than a small one.

## What v1 stores

| Setting | Type | Effect |
|---|---|---|
| **Default events view** | `timeline` \| `calendar` | which view the events screens open on |

## Roles section

**Global roles only in v1** — no per-calendar or per-event managers. Model:
[permissions.md](../permissions.md).

The section lists users holding permissions and lets an admin assign or revoke. The UI is a
user picker, a **checkbox list** rendered straight off `allPermissionSets()`, a list, and a
remove action.

**There are no roles** — a user is granted permission keys directly. Nine in v1, plus
WordPress administrators who bypass the system entirely:

| Permission | Grants |
|---|---|
| `create_calendars` | create new calendars |
| `manage_own_calendars` | own calendars, and the events and guests under them |
| `read_other_calendars` | read-only view of calendars owned by others |
| `manage_other_calendars` | full control of calendars owned by others |
| `read_all_events` / `manage_all_events` | events on any calendar |
| `read_all_guests` / `manage_all_guests` | guests anywhere |
| `manage_settings` | plugin settings only |

No key implies another. Someone who should do everything is granted every checkbox.

Points that matter when building it:

- **Only `manage_settings` may assign permissions.** A manager who could grant themselves
  settings access defeats the split.
- **WordPress administrators bypass the whole system** and must not appear as removable.
  Revoking one here would be a footgun with no undo.
- **Assigning is one `wp_fe_meta` row** — `object_type = 'user'`,
  `meta_key = '_access_permissions'`, value the array. Revoking deletes the row. No WP
  capability is granted and `wp_usermeta` is never touched.
- **Validate every key against `allPermissionSets()`.** This endpoint is the
  privilege-escalation route in the plugin — never persist whatever the request sent.
- **Nothing runs on activation or on load.** Permissions are granted per user, on demand.
  There is no bootstrap step — see [permissions.md](../permissions.md).

## Default events view interacts with localStorage

The view switch already persists per user. Precedence must be explicit:

1. Stored per-user choice wins.
2. This setting seeds users who have no stored choice.

Otherwise the setting appears to do nothing for anyone who has ever touched the toggle.

## Read from WordPress, don't re-ask

- **Date and time formats** — `get_option('date_format')` / `get_option('time_format')`.
  The SPA gets them converted to Day.js tokens; the plugin stores no override.
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
- `FE_DB_VERSION` stays in `wp_options` — the migrator reads it before the tables are
  guaranteed to exist. `fe_general` is not needed until there is a general section.

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
