# Scaffolding

> Everything that comes before Phase 1 feature work. The shell, wired once and correctly,
> so no phase has to stop and fix it.
>
> Code shape follows [docs/patterns/](../../patterns/README.md); the shell's markup, tokens
> and stylesheet split follow [patterns/admin-layout.md](../../patterns/admin-layout.md).
> Permissions follow [permissions.md](permissions.md).

---

## 1. What already exists — do not rebuild

The WPFluent scaffold ships more than it looks. Verified present and working:

| Piece | Where | State |
|---|---|---|
| Plugin bootstrap, constants, autoload | [fluent-event.php](../../../../fluent-event.php) | done |
| Menu + logo + SPA mount | [AdminMenuHandler.php](../../../../app/Hooks/Handlers/AdminMenuHandler.php) | `add_menu_page` + `getMenuIcon()` + `render()` wired |
| Asset enqueue + bootstrap payload | `AdminMenuHandler::localizeScript()` | rich — see §4 |
| Activation → migrations | [ActivationHandler.php](../../../../app/Hooks/Handlers/ActivationHandler.php) | runs `DBMigrator`, with a multisite branch |
| Deactivation | `DeactivationHandler.php` | present |
| Migrator (Blueprint API) | [DBMigrator.php](../../../../database/DBMigrator.php) | done — see [patterns/database.md](../../patterns/database.md) |
| CPT registration point | [CPTHandler.php](../../../../app/Hooks/Handlers/CPTHandler.php) | stubbed, empty `$customPostTypes` |
| Exception handling | `ExceptionHandler.php` | present |
| Vue router, hash history, module glob | [resources/admin/router/](../../../../resources/admin/router/) | done |
| App shell + topbar + theme toggle | `app/Views/admin/menu.php`, `Application.vue` | done — topbar is PHP, `Application.vue` is a bare `router-view` |
| Base classes | `Controller.php`, `Policy.php`, `Model.php` | present |
| Shared components | `Confirm.vue`, `Pagination.vue`, `CursorPagination.vue`, `Menu/` | present |
| Theme palettes | [config/theme.php](../../../../config/theme.php) | light + dark defined |
| i18n map → SPA | `AdminMenuHandler::getTranslationLabels()` ← `config/i18n.php` | wired, filterable |

**The consequence:** scaffolding is mostly *correcting and extending*, not building.

---

## 2. Defects to fix first

Each is small, and each gets worse the more code depends on it.

### `Confirm.vue` — two real bugs

Every destructive action in v1 routes through
[Confirm.vue](../../../../resources/admin/components/Confirm.vue):

1. **Hardcoded English** — the buttons are literal `cancel` and `confirm`, not `$t()`. Every
   use inherits an untranslatable dialog.
2. **`v-html="message"`** — the moment a caller passes `Delete "{{ event.title }}"?`, an
   event title containing markup becomes an injection point, and titles are user input.
   Switch to `{{ }}`, or sanitize server-side before the string reaches the prop.

### Topbar brand — fixed in [#9](https://github.com/WPManageNinja/fluent-event/pull/9)

All three were in `Application.vue`, which no longer renders the topbar at all — the navbar
moved to `app/Views/admin/menu.php`:

- ~~Brand renders a hardcoded `<span>beta</span>` instead of the plugin name.~~ Removed; the
  logo `alt` carries the plugin name from `app.name`.
- ~~Brand links to `/` — the site root, wrong from inside wp-admin.~~ Now `$baseUrl`, the
  dashboard route.
- ~~Right side renders `[ThemeSwitcher][Settings]`; the agreed order is Settings then the
  theme toggle.~~ Now `[theme toggle][Settings]`, matching affiliate's `&__settings` order.

> The order that shipped is the affiliate one — colour-mode toggle first, then the
> right-menu items — which is the reverse of the "Settings then theme toggle" wording above.
> Suite parity won.

### `is_admin` uses a role as a capability

`localizeScript()` sends:

```php
'is_admin' => current_user_can('administrator'),
```

This happens to work — role names end up in `allcaps` — but checking a role as though it
were a capability is explicitly discouraged, and it breaks for any user granted
`manage_options` without the administrator role. Use `current_user_can('manage_options')`,
which is also what [permissions.md](permissions.md) treats as the admin bypass.

---

## 3. Permission layer

Nothing in v1 can be gated until this exists. Build it before the first controller.

- `app/Services/Permission/PermissionManager.php` — `allPermissionSets()`,
  `getUserPermissions()`, `userCan()`, plus the record-scoped `canReadCalendar()` /
  `canWriteCalendar()`. Full spec in [permissions.md](permissions.md).
- Policies extend the base `Policy` and call `PermissionManager::userCan()` — or the
  record-scoped methods when a single record is involved. Never
  `current_user_can` inline in a controller — Tier 2 appends steps to the resolution chain
  later, and it must only touch one file.
- Menu registration becomes conditional (§5).

---

## 4. Bootstrap payload

`localizeScript()` already sends `env`, `slug`, `theme`, `nonce`, `rest`, `endpoints`,
`routes`, `name`, `hook_prefix`, `logoUrl`, `i18n`, and a `me` object.

**`me` is missing the one thing v1's UI needs — permissions.** Without it, no component can
decide whether to render an Add Event button, and the frontend either shows everything or
makes a request per check.

```php
'me' => [
    'id'          => $authUser->ID ?? null,
    'email'       => $authUser->user_email ?? null,
    'full_name'   => $authUser->display_name ?? null,
    'is_admin'    => current_user_can('manage_options'),
        'permissions' => PermissionManager::getUserPermissions(), // add
],
```

Also add the settings the SPA needs at first paint, so no screen has to fetch before it can
render: **date format, time format, default events view, dark-mode enabled**. All four come
from the `customization` row — see [screens/settings.md](screens/settings.md).

> Permissions in the payload are for **rendering only**. They are not a security boundary —
> the policy on each route is. See [permissions.md](permissions.md) §Gating the UI.

---

## 5. Menu and navigation

Two surfaces, both driven by permissions.

### WordPress sidebar — conditional registration

Four entries, in this order:

| Order | Label | Target | Shown to |
|---|---|---|---|
| 1 | Add New Event | `fluent-event#/events/new` | anyone who can create an event, + admin |
| 2 | Calendars | `fluent-event#/calendars` | all with a role |
| 3 | Events | `fluent-event#/events` | all with a role |
| 4 | Settings | `fluent-event#/settings` | `manage_settings` only |

```php
public function add()
{
    if (!PermissionManager::userCan(['manage_own_calendars', 'read_other_calendars', 'manage_other_calendars'])) {
        return;                                   // no role → no menu at all
    }

    add_menu_page(..., 'read', 'fluent-event', [$this, 'render'], $this->getMenuIcon(), $position);

    // Re-register the parent slug so its own entry gets a chosen label
    // instead of repeating the plugin name — see the note below.
    add_submenu_page('fluent-event', __('Dashboard', 'fluent-event'), __('Dashboard', 'fluent-event'),
        'read', 'fluent-event', [$this, 'render']);

    if (PermissionManager::userCan(['manage_own_calendars', 'manage_all_events'])) {
        add_submenu_page('fluent-event', __('Add New Event', 'fluent-event'), __('Add New Event', 'fluent-event'),
            'read', 'fluent-event#/events/new', [$this, 'render']);
    }

    add_submenu_page('fluent-event', __('Calendars', 'fluent-event'), ..., 'fluent-event#/calendars');
    add_submenu_page('fluent-event', __('Events', 'fluent-event'), ..., 'fluent-event#/events');

    if (PermissionManager::userCan('manage_settings')) {
        add_submenu_page('fluent-event', __('Settings', 'fluent-event'), ..., 'fluent-event#/settings');
    }
}
```

**Three things this menu forces:**

1. **The parent slug still needs its own submenu entry.** The top-level *FluentEvent* item
   is clickable and lands on `#/` — the dashboard. Without re-registering the parent slug as
   the first submenu, WordPress labels that entry with the plugin name, so the sidebar reads
   "FluentEvent → FluentEvent, Add New Event, …". Re-registering lets us call it
   *Dashboard*. If you would rather not show it, the alternative is making the top-level item
   land on Calendars instead — but the parent is always clickable, so it always goes
   *somewhere*.
2. **"Add New Event" needs a route that exists** — `#/events/new`. That is a create screen or
   a modal opened by route, and it must be built in Phase 3 alongside the events module, not
   left as a dead link.
3. **"Add New Event" is hidden from read-only users.** An Add button they cannot use is a
   bug report. Gate it on the same permission the button inside the SPA uses.

> The sidebar and the SPA topbar deliberately differ. The sidebar is a launcher — it gets
> *Add New Event* because deep-linking to a create screen from anywhere in wp-admin is
> useful. The topbar is in-app navigation and keeps Dashboard · Calendars · Events, with
> creation happening from a button in context.

### SPA topbar — PHP-rendered

`Http/Routes/vue.php` is gone. The topbar is markup in
[app/Views/admin/menu.php](../../../../app/Views/admin/menu.php), looping arrays from
`AdminMenuHandler::getMenuItemsList()` and `getRightMenuItemsList()`; the routes live
separately in [routes.js](../../../../resources/admin/router/routes.js). A `data-key` on
each wrapper is matched against `meta.active_menu` for active styling.

Adding an entry means editing both — the menu array and the route list:

| Side | Item | `data-key` / `active_menu` | Route | Module |
|---|---|---|---|---|
| middle | Dashboard | `dashboard` | `/` | `modules/dashboard` |
| middle | Calendars | `calendars` | `/calendars` | `modules/calendars` |
| middle | Events | `events` | `/events` | `modules/events` |
| right | Settings | `settings` | `/settings` | `modules/settings` |

`/events/new` also routes to `modules/events/create.vue` under `active_menu: events`, so the
*Add New Event* sidebar entry resolves without a topbar entry of its own. Settings goes
behind the same permission condition as before, once chunk 22b lands.

---

## 6. Directory skeleton

Create the empty structure now so the first feature commit doesn't also invent the layout.
Shape per [patterns/backend.md](../../patterns/backend.md) and
[patterns/frontend.md](../../patterns/frontend.md).

```
app/
├── CPT/                          EventPost.php — registered via CPTHandler
├── Http/Controllers/             Calendar, Event, Guest, Setting, Report
├── Http/Policies/                one per controller
├── Services/                     the bulk of the logic
│   └── Permission/               PermissionManager.php
database/Migrations/              7 Blueprint files
resources/admin/modules/          lowercase — the vite glob is modules/**/*.vue
├── calendars/
├── events/
└── settings/
```

> **Casing trap:** fluent-affiliate uses `Modules/` with a capital M. Our glob is
> `resources/admin/modules/**/*.vue`. Matching affiliate's casing breaks component
> resolution silently.

Filename convention inside a module: no prefix = routable screen, `_Name.vue` = private to
that module, `__Name.vue` = a part of a part.

---

## 7. Uninstall

**There is no `uninstall.php` and no `register_uninstall_hook` today.** With v1 creating 7
tables plus usermeta rows, that gap ships real cruft.

Decide and implement the policy before release:

- Delete the `_access_permissions` rows from `wp_fe_meta` — one `DELETE`, always safe.
- Tables and CPT posts: **default to keeping them.** Silent data destruction on uninstall is
  worse than leftovers. Gate any dropping behind an explicit opt-in.

Note this was considered as a setting and dropped for v1 — so v1's uninstall should clean up
meta only.

---

## 8. Done when

Scaffolding is finished when all of these pass:

- `./wpf check` runs clean.
- The **FluentEvent** menu appears for an administrator, with Dashboard, Calendars, Events,
  Settings.
- A user holding only `read_other_calendars` sees the menu **without** Settings or Add New Event.
- A user with no role sees no menu, and `admin.php?page=fluent-event` is not reachable.
- The SPA boots, the topbar renders brand + primary nav + Settings + theme toggle, and
  `window.fluentEventAdmin.me.permissions` is populated.
- `./wpf migrate:refresh` builds all 7 tables and `./wpf db:show` lists them.

> `./wpf check:boot` reports `plugin.php: Entry point not found` — devtools hardcodes that
> filename while ours is `fluent-event.php`. Pre-existing since `7299f30`, unrelated to
> scaffolding, and not a blocker.
