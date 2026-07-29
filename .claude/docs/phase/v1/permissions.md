# Permissions model

> Follows **fluent-cart**'s `PermissionManager` — the newest house pattern, and the one
> FR-4.4 already describes — with its capability dropped. See
> [Why no capability](#why-no-capability).

**v1 ships global permissions only.** No per-calendar or per-event roles.

---

## Shape

**One usermeta row per privileged user. Nothing else.**

| Piece | Where | Purpose |
|---|---|---|
| The user's role — a single string | `wp_usermeta` → `_fevent_role` | what they may do |
| Role definitions | **PHP array in code**, filterable | what each role means |

No WP role is created, no capability is granted, `wp_options.wp_user_roles` and
`wp_capabilities` are never touched.

```php
class PermissionManager
{
    public static $metaKey = '_fevent_role';   // 'fevent_manager' | 'fevent_viewer'
}
```

The meta key is underscore-prefixed so `is_protected_meta()` treats it as internal — it
stays out of REST responses and meta-editor UIs. A user-editable role string would be a
privilege-escalation bug.

---

## Permissions and roles

Permissions are `noun/verb` strings, matching fluent-cart's convention:

```
calendars/view      calendars/manage      calendars/delete
events/view         events/manage         events/delete
guests/view
settings/manage
```

### Who gets what in v1

| | WP administrator | `fevent_manager` | `fevent_viewer` |
|---|---|---|---|
| How identified | `manage_options` | `_fevent_role` | `_fevent_role` |
| Settings | ✓ | ✓ | — |
| Calendars — view | ✓ | ✓ | ✓ |
| Calendars — create / edit / delete | ✓ | ✓ | — |
| Events — view | ✓ | ✓ | ✓ |
| Events — create / edit / delete | ✓ | ✓ | — |
| Guests — view | ✓ | ✓ | ✓ |

Administrators bypass the system entirely — they hold no `_fevent_role` row and never need
one. `fevent_viewer` is **read-only everywhere**: it may look at calendars, events and
guests, and change nothing.

### Definition

```php
public static function getAllRoles(): array
{
    $roles = [
        'fevent_manager' => [
            'title'       => __('Manager', 'fluent-event'),
            'description' => __('Full access, including settings', 'fluent-event'),
            'permissions' => array_keys(self::getAllPermissions()),
        ],
        'fevent_viewer' => [
            'title'       => __('Viewer', 'fluent-event'),
            'description' => __('Read-only access to calendars, events and guests', 'fluent-event'),
            'permissions' => [
                'calendars/view',
                'events/view',
                'guests/view',
            ],
        ],
    ];

    return apply_filters('fluent_event/permission/all_roles', $roles, []);
}
```

`fevent_manager` uses `array_keys(getAllPermissions())` rather than a hand-written list, so
a permission added later is granted to it automatically and cannot be forgotten.

Two roles is enough for v1. Adding a third is a code change, not a migration — which is the
main thing this pattern buys.

---

## Checking

```php
PermissionManager::hasPermission('events/manage');            // current user
PermissionManager::hasPermission('events/delete', $userId);   // a specific user
```

```php
public static function hasPermission($permission, $userId = false)
{
    $userId = $userId ?: get_current_user_id();
    if (!$userId) {
        return false;
    }

    if (user_can($userId, 'manage_options')) {   // admins bypass
        return true;
    }

    $role = get_user_meta($userId, static::$metaKey, true);
    if (!$role) {
        return false;
    }

    return in_array($permission, Arr::get(self::getAllRoles(), $role . '.permissions', []));
}
```

**The admin bypass is not optional.** Without it a site owner who never promoted themselves
is locked out of their own plugin.

**Policies call this, controllers do not** — one place to change when per-object rules
arrive:

```php
class CalendarPolicy extends Policy
{
    public function verifyRequest(Request $request)
    {
        return PermissionManager::hasPermission('calendars/view');
    }

    public function destroy(Request $request)   // destructive → its own method
    {
        return PermissionManager::hasPermission('calendars/delete');
    }
}
```

Cost is nil: the role meta arrives in the same cached `update_meta_cache()` call that
WordPress already makes for the current user, so checks are in-memory. No memoization
needed.

---

## Granting and revoking

```php
// promote — validate the role against getAllRoles() first, never trust the request
update_user_meta($userId, PermissionManager::$metaKey, 'fevent_manager');

// revoke
delete_user_meta($userId, PermissionManager::$metaKey);
```

One write each way. Both happen from the Roles section in settings —
[screens/settings.md](screens/settings.md).

**Nothing runs on activation, and nothing runs on load.** There is no bootstrap step at all.

**The grant endpoint is the privilege-escalation route in the plugin.** It must require
`settings/manage`, and it must reject any role string not present in `getAllRoles()` —
otherwise a caller can write an arbitrary value that a future release might make real.

---

## Gating the UI

Three layers, each doing a different job. Only the last is security.

### 1. The menu — conditional registration

`add_menu_page()` requires a capability string, but we don't need our own: pass `read` and
**guard the registration itself**.

```php
public function add()
{
    if (!PermissionManager::hasPermission('calendars/view')) {
        return;                       // no role → no menu at all
    }

    add_menu_page(..., 'read', 'fluent-event', [$this, 'render'], ...);

    if (PermissionManager::hasPermission('settings/manage')) {
        add_submenu_page(..., 'read', 'fluent-event#/settings', ...);   // viewers never see it
    }
}
```

An unregistered page is unreachable by URL too — `wp-admin/admin.php` looks up the page
hook and dies when there isn't one for this request. So a subscriber guessing
`admin.php?page=fluent-event` gets nothing.

### 2. The SPA nav and buttons

The topbar is server-declared in
[app/Http/Routes/vue.php](../../../../app/Http/Routes/vue.php), so the Settings entry is
registered under the same condition — a viewer never receives the route.

Action buttons (Add Event, Delete) are `v-if` on permission flags sent with the bootstrap
payload.

### 3. The policies — the only real enforcement

Every write route checks its permission. **Hiding a button is not enforcement**: a viewer
who hand-crafts a `DELETE` must be refused by the policy. Layers 1 and 2 are courtesy;
this one is the security boundary.

---

## Why no capability

fluent-cart defines `ADMIN_CAP = 'fluent_cart_admin'` and grants it alongside the role. We
looked at what it actually does there — it appears in exactly one place:

```php
$capability = 'manage_options';
if (!current_user_can('manage_options') && App::isProActive()) {
    $capability = PermissionManager::ADMIN_CAP;
}
if (!current_user_can($capability)) {
    return;                              // ← the guard does the work
}
add_menu_page(..., $capability, ...);
```

The early return already gates the menu; the capability only supplies *a string a non-admin
staffer holds* to pass into `add_menu_page`. It exists because fluent-cart has a free/pro
split that needs a per-tier capability. We have no such split.

Dropping it costs one cosmetic thing — `read` is a slightly odd capability to hand
`add_menu_page` — and one real thing: third-party code can't `current_user_can('fevent_…')`
and must call `PermissionManager` instead. A filter is a cheaper way to offer that if it is
ever wanted.

What it saves:

- **One write instead of two** on promote, one instead of two on revoke.
- **WordPress's serialized `wp_capabilities` array is never modified**, so no
  `wp_user_level` rewrite side effect either.
- **No possibility of the two stores disagreeing** — a capability without a role, or a role
  without a capability, are both broken states that simply cannot occur.
- **Nothing to clean up on uninstall** beyond deleting the meta rows. Your own install shows
  what sloppy capability cleanup leaves behind: `subscriber` still carries
  `read_private_events`, `contributor` still carries `edit_events` and `manage_bookings`,
  from event plugins that never removed them.

### Also considered: native WP roles

An earlier draft registered `fevent_manager` with `add_role()` into
`wp_options.wp_user_roles`. Standard WordPress, and WooCommerce does it — but it carries
four burdens this pattern avoids entirely: registration must run on activation and never on
`init`; a capability added in v1.1 never reaches existing installs without a version stamp;
multisite needs a per-blog loop; and uninstall must remove the role and its capabilities.

The one thing given up is that the role is not assignable from the WordPress user editor. We
are building a Roles UI anyway, so that costs nothing.

---

## Tier 2 — per-object roles · **not in v1**

The agreed destination, documented so v1 does not paint over it.

Per-calendar and per-event managers live in the existing `calendars.settings` /
`event_details.settings` JSON:

```json
{ "managers": [ { "user_id": 12, "role": "manager" } ] }
```

Resolution becomes, first match wins:

1. `manage_options` — WP admin
2. Global role permissions (above)
3. Owner — `calendars.created_by`, `wp_posts.post_author`
4. Calendar `settings.managers` — cascades to that calendar's events
5. Event `settings.managers`

v1 implements 1–2 only; owner checks arrive with Tier 2. Because every check already goes
through `PermissionManager::hasPermission()`, the later steps append in one file rather than
across every controller.

### Why JSON and not a pivot table

A pivot table was considered and rejected. fcom needs `fcom_space_user` because that table
**is its membership list** — thousands of rows per space, hot indexed queries. We do not
track membership at all: attendees live in `guests`, and attendees are not managers. Only a
handful of grants will exist per object, so JSON fits.

**Known weak spot:** "list everything user X manages" becomes an unindexed cross-row
`JSON_CONTAINS` scan. Acceptable at this scale; promote to a pivot table only if grants grow
large or a dashboard needs that query indexed.

**JSON enforces nothing**, so the application must dedupe manager entries and validate
`user_id` and `role` on every write.
