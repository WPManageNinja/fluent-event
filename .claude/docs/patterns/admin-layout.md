# Admin layout — the wp-admin shell

Reference: **fluent-affiliate**. Copy its shell structure, spacing, and token system.

> **Vue is Options API only** — scanner-enforced ([vue.md](../../rules/vue.md)). Affiliate is
> 120 of 153 `.vue` files on Options API; only 3 use `<script setup>`, and its
> `Application.vue` happens to be one. Copy the *layout*, not those three files' style.

---

## Page structure

We render the shell in [menu.php](../../../app/Views/admin/menu.php), mirroring affiliate
class-for-class with our prefix:

```html
<div id="fluent-event-app" class="fe_wrapper">
    <div class="fe-navbar"> … </div>
    <div class="fe_body">
        <div id="fluent-event-router" class="fe_route_wrapper"></div>
    </div>
</div>
```

Navbar sits **outside** the Vue mount point; the SPA renders only into
`#fluent-event-router`. The mount id is built from the plugin slug, so PHP and
[start.js](../../../resources/admin/start.js) agree without a hardcoded literal on either
side.

### Where the navbar lives — settled: PHP

**Affiliate renders its navbar in PHP** — `app/Views/admin/menu.php`, looping
`$menuItems` / `$rightMenuItems` arrays passed from the menu handler. **We now do the same.**

The scaffold originally rendered it in Vue, fed by a server-declared route tree
(`Http/Routes/vue.php` → `getAdminRoutes()` → a localized `routes` key). That was one
`->add()` call per nav item, which read well — but the topbar only existed once the SPA
bundle parsed and mounted, so every page load showed a blank frame where the chrome should
be, and the layout matched no other plugin in the suite.

**PHP wins on both counts.** wp-admin paints the topbar with the page, and the markup is the
same shape as affiliate's. The cost is the one affiliate already pays: the menu array in
`AdminMenuHandler::getMenuItemsList()` / `getRightMenuItemsList()` and the route list in
[routes.js](../../../resources/admin/router/routes.js) are maintained separately, joined
only by `meta.active_menu` matching a `data-key`. Adding a nav item means touching both.

A screen that also belongs in the **wp-admin sidebar** takes a third edit — an
`addSubmenu()` call. Pass a bare route (`'calendars'`, not `'#/calendars'`): the handler
builds the entry from `$this->baseUrl`, which already ends in `#/`, and registers it as an
absolute URL so WordPress prints the fragment verbatim instead of percent-encoding it
through `add_query_arg`. Only the root entry passes the plain page slug — it is the
registered page, and matching the parent slug is what stops WordPress auto-inserting its
own duplicate first item. Highlighting needs no per-item work; see below.

Removed with the switch: `Http/Routes/vue.php`, the `getAdminRoutes` / `getMenuItems` /
`registerRoutesAndMenu` chain, the localized `routes` key, and `ThemeSwitcher.vue`.
`Application.vue` is now just a `router-view`.

---

## Navbar — BEM structure

Affiliate's `.fa-navbar`, worth mirroring class-for-class with our prefix:

```
.fa-navbar                    sticky, flex, space-between, z-index 99
├── .fa_mobile_menu_opener    hamburger + collapsed link list (mobile only)
├── &__logo
│   └── &__logo-img           two <img>: .fa_light_logo + .fa_dark_logo
│   └── &__beta-tag           optional, conditional on a $beta flag
├── &__links.fa_desktop_only
│   └── &__link-wrapper       [data-key="…"]
│       ├── &__link
│       ├── &__dropdown-icon  only when the item has sub_items
│       └── &__dropdown
│           └── &__dropdown-item
└── &__settings               right side: colour-mode toggle, then right-menu items
```

Details worth keeping:

- **`position: sticky; top: 0`** with `z-index: 99` — the bar survives scrolling inside
  wp-admin.
- **`data-key` on every wrapper** — gives CSS and tests a stable hook that does not depend
  on label text, which is translated.
- **Dropdowns are CSS-only**, opened on hover/focus of `__link-wrapper`. No JS state.
- **`&:has(+ .fframe_body .fa_onboarding_wrapper) { display: none }`** — the navbar hides
  itself during onboarding. Neat trick; no JS coordination.

**What we shipped.** `.fe-navbar` with `__logo` / `__logo-img` / `__links` /
`__link-wrapper` / `__link` / `__settings`, sticky at `z-index: 99`, `data-key` on every
wrapper. Not built yet: the mobile opener (`Icon::MENU` is defined and waiting), the
dropdown tier — no nav item has sub-items — and the onboarding `:has()` rule.

The `beta` span is gone rather than gated; affiliate keys its `&__beta-tag` off a `$beta`
flag, and we have no such flag to key off.

Active-link state is applied by a router `afterEach` in
[start.js](../../../resources/admin/start.js), which matches `to.meta.active_menu` against
`data-key` and sets both `--active` and `aria-current="page"`.

The same hook re-marks the **wp-admin sidebar**, which cannot track us on its own: WordPress
resolves the current submenu server-side from `$_GET['page']`, the hash route never reaches
the server, and hash navigation never reloads the page — so it would leave Dashboard lit
forever. Three things that look incidental but are not:

- **Match on the query, not the whole URL.** WordPress prints routed entries absolute but
  the root entry relative, so only the `?page=…` tail is common to both.
- **Derive it from `config.baseUrl`**, the same filtered value the entries were registered
  with — rebuilding it from the slug breaks any install that filters the base.
- **Scope by page slug and anchor at the end.** `#/calendars` alone also matches Fluent
  Booking's submenu; `href*=` would let `#/events` match `#/events/new`.

Matching is two-tier: the exact route first, so `/events/new` marks *Add New Event* rather
than *Events*, falling back to the `active_menu` group so nested screens like
`/calendars/5/edit` stay under *Calendars*.

---

## Design tokens

Affiliate defines CSS custom properties in `resources/scss/variables.scss`, one block per
mode:

| Token | Light | Dark |
|---|---|---|
| `--fe-primary-text` | `#19283a` | `#F0F3F5` |
| `--fe-secondary-text` | `#525866` | `#99A0AE` |
| `--fe-primary-bg` | `#ffffff` | `#151D26` |
| `--fe-secondary-bg` | `#f0f2f5` | `#11171D` |
| `--fe-active-bg` | `#f0f3f5` | `#1C2732` |
| `--fe-primary-border` | `#d3d8de` | `#253241` |
| `--fe-secondary-border` | `#9CA3AF` | `#2C3C4E` |
| `--fe-primary-button` | `#2B2E33` | `#FFFFFF` |
| `--fe-badge-color` | `#E1E4EA` | `#2C3C4E` |

Nine tokens covering both modes. That is the whole palette — worth matching in scale, not
just approach. Every component reads the variable with a fallback:
`var(--fe-primary-bg, #ffffff)`.

**Shipped as-is** in [variables.scss](../../../resources/scss/variables.scss) — the nine
tokens above, plus spacing (`--fe-space-1…12`), radius, font-size and `--fe-navbar-height`.
Light on `body`, dark on `html.dark body`.

> `html.dark body`, not `html.dark`, is deliberate. The light block sits on `body`, and an
> element's own declaration beats an inherited one — a plain `html.dark` would lose to it.
> If the light tokens ever move to `:root`, the dark block can drop `body` too.

Element Plus's own `--el-*` variables are redeclared in the same two blocks, so EP
components theme with everything else.

**`config/theme.php` is not the source.** It still exists and `localizeScript` still ships
a `theme` key, but the tokens are literals in SCSS — nothing reads the server palette. Wiring
one to the other would make the palette filterable; until something needs that, the extra
indirection buys nothing.

---

## Stylesheet split

```
resources/scss/
├── variables.scss     tokens, light + dark blocks
├── globals.scss       resets, wp-admin overrides
├── navigation.scss    the navbar
├── application.scss   shell / body layout
├── component.scss     shared component styling
├── table.scss         list/table styling
├── settings.scss      settings screens
├── rtl.scss           right-to-left overrides
└── vendor.scss        Element Plus overrides
```

**Ours now:**

```
resources/scss/
├── admin.scss         the entry — @use's the rest, order matters, later wins
├── variables.scss     tokens, light + dark blocks
├── globals.scss       resets, wp-admin overrides
├── navigation.scss    the navbar
├── app.scss           shell / body layout
└── vendor.scss        Element Plus overrides
```

`app.scss` is affiliate's `application.scss` under a shorter name. `elements/_responsive.scss`
was empty and is gone; `component.scss`, `table.scss` and `settings.scss` arrive with the
screens that need them.

`vendor.scss` pulls EP's stylesheets with `@use "sass:meta"` +
`meta.load-css(...)` rather than JS imports, so `el-notification`, `el-loading`,
`el-message-box` and `dark/css-vars` land in `admin.css` in a known order instead of
wherever the bundler puts them.

**`rtl.scss` is worth copying early.** Retrofitting RTL after every screen exists is far
more work than keeping a parallel file from the start.

---

## Dark mode

Affiliate does it without JS state:

- Two logos in the DOM (`.fa_light_logo`, `.fa_dark_logo`), CSS shows one.
- Two inline SVGs for the toggle icon (`.fa_light_icon`, `.fa_dark_icon`), same trick.
- `toggleColorMode()` flips a class on a root element; the token block does the rest.

**We copied it.** `ThemeSwitcher.vue` — an `el-dropdown` with light/dark/system and a table
of EP colour variables — is deleted. The toggle is a PHP `<button>` in the navbar calling
`toggleColorMode()`, with `Icon::SUN` / `Icon::MOON` swapped by CSS transform, and both logo
`<img>`s in the DOM.

Two places we diverge from affiliate, both deliberate:

- **`<html>` is the only state carrier.** Affiliate also writes the class onto
  `#wpbody-content`, but no stylesheet in either plugin reads it — every dark selector is
  `html.dark`, EP's own `dark/css-vars.css` included. We write one element.
- **`light` and `dark` are mutually exclusive.** Toggling only `dark` while a restore path
  adds `light` leaves both classes on the element.

Persisted as `localStorage.fe_color_mode`. `toggleColorMode` lives in
[app.js](../../../resources/admin/app.js), which is enqueued in the footer — so, exactly as
in affiliate, the button is inert until that bundle parses and a dark-mode user sees one
light frame first. Fixing it means inlining the apply in the view or on `admin_head`.

The light logo is `logo.svg` (dark wordmark), the dark one `logo-dark.svg` (white
wordmark) — the only difference is the text fill.

Remember dark mode is gated by a setting in v1 — when it is off, the toggle should not
render at all. **Not yet done**: the toggle renders unconditionally until chunk 21 gives it
a setting to read. See [screens/settings.md](../phase/v1/screens/settings.md).

---

## Shared UI pieces

Affiliate keeps a deliberately small set:

| Piece | Purpose |
|---|---|
| `Components/Layout/PageLoadingSkeleton.vue` | skeleton for any page's first paint |
| `Components/UIElements/FaActionPanel.vue` | the header strip: title left, actions right |
| `Components/UIElements/FaFilterGroup.vue` | filter controls above a list |
| `Pieces/Pagination.vue` | one pagination component for every list |
| `Charts/AffiliateGrowth.vue` | chart taking an endpoint as a prop |

Five shared pieces against seven feature modules. That ratio is the point — see the
extraction rule in [README.md](README.md). Build the equivalent of `ActionPanel` and
`FilterGroup` early, since every list screen in v1 needs both.

---

## What to build first

In [scaffolding.md](../phase/v1/scaffolding.md) terms:

1. ~~`variables.scss` with our nine tokens, light + dark.~~ Done.
2. ~~`navigation.scss` ported from affiliate, class names re-prefixed.~~ Done.
3. ~~Fix the three topbar defects — brand name, dashboard link, control order.~~ Done.
4. `PageLoadingSkeleton`, `ActionPanel`, `FilterGroup` — needed by every screen after.

Carried forward from the topbar work, none of it blocking:

- Mobile menu opener — `Icon::MENU` exists, no markup uses it.
- Gate the colour-mode toggle on the v1 dark-mode setting (chunk 21).
- Dropdown tier in the navbar, once a nav item has sub-items.
- `rtl.scss`, before the screens pile up.
