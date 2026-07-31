# Permissions model

> Follows **fluent-booking**'s `PermissionManager`: a flat list of module-scoped permission
> sets, assigned per user. **No roles.**
>
> **v1 ships global permissions only** — no per-calendar or per-event grants.

---

## Shape

| Piece | Where |
|---|---|
| A user's granted permissions — an array | one `wp_fe_meta` row per user |
| The master list of permissions | **PHP array in code**, filterable |

The row, matching booking's exactly apart from column names:

| Column | Value |
|---|---|
| `object_type` | `user` |
| `object_id` | the WP user id |
| `meta_key` | `_access_permissions` |
| `value` | the permission array, PHP-serialized by the ORM |

A real row from `wp_fcal_meta` on this install, for reference:

```
object_type  user_meta
object_id    211
key          _access_permissions
value        a:2:{i:0;s:19:"manage_own_calendar";i:1;s:17:"read_all_bookings";}
```

**No row means no permissions.** There is nothing to seed and nothing to default.

`value` is serialized rather than JSON because that is what the ORM does when casting an
array — same as booking. Do not hand-write the column.

No WP role is created, no capability is granted, and `wp_usermeta` is never touched.
Booking stores this the same way, in its own `fcal_meta` table rather than user meta.

**Using `wp_fe_meta` rather than `wp_usermeta` is deliberate:** plugin data stays in plugin
tables, so uninstall is one `DELETE` on a table we own rather than a scan across every
user's meta. The table was designed for it — `object_type = 'user'` is in its column
comment.

---

## Permission sets

Module-scoped, coarse, each with a label the Roles UI renders directly. Mirrors booking's
`allPermissionSets()`:

```php
public static function allPermissionSets(): array
{
    $sets = [
        'create_calendars'       => __('Create new Calendars', 'fluent-event'),
        'manage_own_calendars'   => __('Manage only own Calendars, Events and Guests', 'fluent-event'),

        'read_other_calendars'   => __('Read access to other Calendars', 'fluent-event'),
        'manage_other_calendars' => __('Manage other Calendars', 'fluent-event'),

        'read_all_events'        => __('Read access to all Events', 'fluent-event'),
        'manage_all_events'      => __('Read and write access to all Events', 'fluent-event'),

        'read_all_guests'        => __('Read access to all Guests', 'fluent-event'),
        'manage_all_guests'      => __('Read and write access to all Guests', 'fluent-event'),

        'manage_settings'        => __('Manage plugin settings', 'fluent-event'),
    ];

    return apply_filters('fluent_event/permission/all_sets', $sets, []);
}
```

Nine sets, mirroring booking's shape: **own versus others, read versus manage, per
module**.

**`create_calendars` is deliberately separate from `manage_own_calendars`.** Creating is a
different power from tending what you already have: revoking the create key leaves someone
looking after their existing calendars without being able to spawn more. Bundled together
there would be no way to express that — revoking would strip both.

A new host is normally granted the pair. Only the create key is taken away later.

**There is no catch-all key.** `manage_settings` grants settings and nothing else — someone
who should do everything is granted every key. A single key that silently implies all the
others is how a settings grant turns into full access by accident.

**Permissions are the unit — there is nothing between them and the user.** A user needing
their own calendars plus read of everyone else's is granted both keys.

The label is part of the definition, not a separate translation table, because the UI is a
checkbox list rendered straight off this array.

### Example grants

| User | Granted | Can |
|---|---|---|
| A new host | `create_calendars`, `manage_own_calendars` | Create calendars; edit and delete the ones they created, with the events and guests under them. Sees nobody else's |
| A wound-down host | `manage_own_calendars` | Keeps running the calendars they already own. **Cannot create any more** |
| A coordinator | `create_calendars`, `manage_own_calendars`, `read_other_calendars` | The above, plus read-only view of every other calendar |
| An events manager | `create_calendars`, `manage_own_calendars`, `manage_all_events` | Their own calendars, plus full control of events on any calendar |
| A front-desk user | `read_other_calendars`, `manage_all_guests` | Every calendar read-only; manages guest responses anywhere. Cannot create or edit events |
| An auditor | `read_other_calendars`, `read_all_events`, `read_all_guests` | Sees everything, changes nothing |
| A settings admin | `manage_settings` | Plugin settings only — no calendars, events or guests |
| A full deputy | every key | Everything. There is no shortcut for this |

The stored row for the first of those:

```
object_type  user
object_id    211
meta_key     _access_permissions
value        a:2:{i:0;s:16:"create_calendars";i:1;s:20:"manage_own_calendars";}
```

### "Own" needs a real ownership check

`manage_own_calendars` cannot be answered by a key lookup — it needs the record. Ownership
is `calendars.created_by` and `wp_posts.post_author`.

So `PermissionManager` has two tiers, as booking's does:

```php
// tier 1 — flat key check, no record involved
PermissionManager::userCan('manage_all_events');

// tier 2 — resolves own versus others, needs the id
PermissionManager::canReadCalendar($calendarId);
PermissionManager::canWriteCalendar($calendarId);
```

```php
public static function canReadCalendar($calendarId): bool
{
    if (current_user_can('manage_options')) {
        return true;
    }

    $calendar = Calendar::find($calendarId);
    if (!$calendar) {
        return false;
    }

    if ($calendar->created_by == get_current_user_id()) {   // owner
        return true;
    }

    return self::userCan(['read_other_calendars', 'manage_other_calendars']);
}
```

**Anything scoped to a single record goes through the tier-2 method, never `userCan()`
alone.** A policy checking only the key grants every host access to every other host's
calendar — the exact bug this model exists to prevent.

**Creation is the one action with no record to check.** There is nothing to own yet, so
`create_calendars` is a plain `userCan()` — the only calendar action that is. Everything
afterwards goes through tier 2.

**Events and guests inherit their calendar's ownership** unless an all-scoped key says
otherwise. A host who owns a calendar manages the events under it without holding
`manage_all_events`; that key is what lets someone touch events on calendars they do not
own.

---

## Checking

```php
public static function getUserPermissions($userId = null): array
{
    $userId = $userId ?: get_current_user_id();
    if (!$userId) {
        return [];
    }

    if (user_can($userId, 'manage_options')) {         // admins bypass
        return array_keys(self::allPermissionSets());
    }

    $meta = Meta::where('object_type', 'user')
        ->where('object_id', $userId)
        ->where('meta_key', '_access_permissions')
        ->first();

    return $meta ? (array) $meta->value : [];
}

public static function userCan($permissions): bool
{
    $permissions = (array) $permissions;

    return (bool) array_intersect($permissions, self::getUserPermissions());
}
```

`userCan()` takes an array and passes on **any** match, as booking's does — so a route can
say "either of these will do" without the caller composing logic.

**The admin bypass is not optional.** Without it a site owner who never granted themselves
anything is locked out of their own plugin.

**An unknown permission key contributes nothing** rather than throwing, so a key removed in
a later release degrades to less access instead of a fatal.

**Policies call this; controllers do not** — one place to change when per-object rules
arrive:

```php
class CalendarPolicy extends Policy
{
    public function verifyRequest(Request $request)
    {
        return PermissionManager::userCan([
            'manage_own_calendars', 'read_other_calendars', 'manage_other_calendars'
        ]);
    }

    public function destroy(Request $request)   // destructive → its own method
    {
        // tier 2: owning the calendar is enough; otherwise an all-scoped key is required
        return PermissionManager::canWriteCalendar($request->get('id'));
    }
}
```

---

## Granting and revoking

One `wp_fe_meta` row per user, written whole:

```php
// grant — validate every key against allPermissionSets() first
Meta::updateOrCreate(
    ['object_type' => 'user', 'object_id' => $userId, 'meta_key' => '_access_permissions'],
    ['value' => ['manage_own_calendars', 'read_other_calendars']]
);

// revoke everything
Meta::where('object_type', 'user')
    ->where('object_id', $userId)
    ->where('meta_key', '_access_permissions')
    ->delete();
```

**Nothing runs on activation, and nothing runs on load.** There is no bootstrap step.

**The grant endpoint is the privilege-escalation route in the plugin.** It must require
`manage_settings`, and it must reject any key not present in `allPermissionSets()` —
otherwise a caller writes arbitrary values that a future release might make real.

---

## The trade this accepts

**No bulk change.** Add `export_guests` in a later release and every existing user needs it
granted individually — there is no role definition to edit once. The sets also drift: after
a year of different admins granting access, no two users have the same array and nobody can
say what "a manager" can do.

Both are real, and both are survivable here:

- It **fails closed**. A new permission nobody holds means nobody can do the new thing until
  granted — annoying, never dangerous.
- The permission sets are **coarse and module-scoped**, so there are eight of them, not
  thirty. Drift across eight checkboxes is manageable in a way drift across thirty is not.
- fluent-booking ships exactly this with eight sets, which is the evidence that it holds at
  this scale.

If a site ever needs named bundles, the cheap answer is **UI presets** — buttons that tick
a set of checkboxes — with nothing role-shaped stored. No migration, because the data shape
does not change.

---

## Why not the alternatives

**Named roles, one per user** — solves bulk change, but a user needing two roles' worth of
access cannot have it without inventing a third role.

**Multiple roles per user** — composable and bulk-editable, but adds a layer between user
and permission that eight coarse sets do not need.

**Native WP roles via `add_role()`** — registration must run on activation and never on
`init`, a capability added later never reaches existing installs without a version stamp,
multisite needs a per-blog loop, and uninstall must clean up. Your own install shows what
happens when it does not: `subscriber` still carries `read_private_events` from an event
plugin that never removed it.

**A WP capability as a gate** — fluent-cart does this, but only because `add_menu_page()`
needs a capability string. We pass `read` and guard the registration instead, which needs no
persisted capability at all.

---

## Gating the UI

Three layers. Only the last is security.

1. **Menu** — `add_menu_page()` needs a capability string, so pass `read` and guard the
   registration with `userCan()`. An unregistered page is unreachable by URL too:
   `wp-admin/admin.php` dies when there is no page hook for the request.
2. **SPA nav and buttons** — the topbar is PHP, so gate each entry as it is pushed into
   `AdminMenuHandler::getMenuItemsList()` / `getRightMenuItemsList()`. Action buttons are
   `v-if` on permission flags from the bootstrap payload.
3. **Policies** — every write route checks. **Hiding a button is not enforcement**: a
   read-only user who hand-crafts a `DELETE` must be refused here.

---

## Tier 2 — per-object grants · **not in v1**

Per-calendar and per-event access in the existing `calendars.meta` / `event_details.meta`
JSON:

```json
{ "managers": [ { "user_id": 12, "permissions": ["manage_events"] } ] }
```

Resolution becomes, first match wins:

1. `manage_options` — WP admin
2. Owner — `calendars.created_by`, `wp_posts.post_author` (already in v1 via tier 2)
4. Global permission sets (above)
5. Calendar `meta.managers` — cascades to that calendar's events
6. Event `meta.managers`

v1 implements 1–3. Because every check already goes through `PermissionManager::userCan()`,
the later steps append in one file rather than across every controller.

### Why JSON and not a pivot table

fcom needs `fcom_space_user` because that table **is its membership list** — thousands of
rows per space, hot indexed queries. We do not track membership at all: attendees live in
`guests`, and attendees are not managers. Only a handful of grants will exist per object.

**Known weak spot:** "list everything user X manages" becomes an unindexed cross-row
`JSON_CONTAINS` scan. Acceptable at this scale; promote to a pivot table only if grants grow
large or a dashboard needs that query indexed.

**JSON enforces nothing**, so the application must dedupe entries and validate `user_id` and
every permission key on write.
