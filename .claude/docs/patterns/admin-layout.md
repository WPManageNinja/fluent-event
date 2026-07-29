# Admin layout — the wp-admin shell

Reference: **fluent-affiliate**. Copy its shell structure, spacing, and token system.

> **Vue is Options API only** — scanner-enforced ([vue.md](../../rules/vue.md)). Affiliate is
> 120 of 153 `.vue` files on Options API; only 3 use `<script setup>`, and its
> `Application.vue` happens to be one. Copy the *layout*, not those three files' style.

---

## Page structure

Affiliate renders the shell as:

```html
<div class="fframe_wrapper">
    <div id="fa_aff_menu" class="fa-navbar"> … </div>
    <div class="fframe_body">
        <div id="fluent-framework-app" class="fs_route_wrapper"></div>
    </div>
</div>
```

Navbar sits **outside** the Vue mount point; the SPA renders only into
`#fluent-framework-app`.

### Where the navbar lives — the one real divergence

**Affiliate renders its navbar in PHP** — `app/Views/admin/menu.php`, looping
`$menuItems` / `$rightMenuItems` arrays passed from the menu handler.

**Our scaffold renders it in Vue** —
[Application.vue](../../../resources/admin/components/Application.vue) has `navbar-left` /
`navbar-right` and pulls items from the `MenuApi`, fed by
[vue.php](../../../app/Http/Routes/vue.php).

**Keep ours.** Our approach is newer and already wired: server-declared routes become both
the router and the menu, so a nav item is one `->add()` call. Affiliate maintains a PHP menu
array *and* a route list separately. Port affiliate's **markup structure, class names and
SCSS**; do not move rendering back into PHP.

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

> Our scaffold already has the `beta` span that I earlier called a stray hardcode. It is
> copied from affiliate's `&__beta-tag` — **but affiliate gates it on a `$beta` flag.** Ours
> renders it unconditionally. Either gate it or remove it.

---

## Design tokens

Affiliate defines CSS custom properties in `resources/scss/variables.scss`, one block per
mode:

| Token | Light | Dark |
|---|---|---|
| `--fla-primary-text` | `#19283a` | `#F0F3F5` |
| `--fla-secondary-text` | `#525866` | `#99A0AE` |
| `--fla-primary-bg` | `#ffffff` | `#151D26` |
| `--fla-secondary-bg` | `#f0f2f5` | `#11171D` |
| `--fla-active-bg` | `#f0f3f5` | `#1C2732` |
| `--fla-primary-border` | `#d3d8de` | `#253241` |
| `--fla-secondary-border` | `#9CA3AF` | `#2C3C4E` |
| `--fla-primary-button` | `#2B2E33` | `#FFFFFF` |
| `--fla-badge-color` | `#E1E4EA` | `#2C3C4E` |

Nine tokens covering both modes. That is the whole palette — worth matching in scale, not
just approach. Every component reads the variable with a fallback:
`var(--fla-primary-bg, #ffffff)`.

**Ours differ in source.** We already have [config/theme.php](../../../config/theme.php)
defining named light/dark palettes server-side, delivered through `localizeScript`. Keep
that — it makes the palette filterable — but adopt affiliate's *token names and coverage*
so components never reach for a hex literal.

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

Ours currently has `admin.scss`, `globals.scss`, `vendor.scss` and
`elements/_responsive.scss`. Add `variables`, `navigation` and `application` at minimum —
those three carry the shell.

**`rtl.scss` is worth copying early.** Retrofitting RTL after every screen exists is far
more work than keeping a parallel file from the start.

---

## Dark mode

Affiliate does it without JS state:

- Two logos in the DOM (`.fa_light_logo`, `.fa_dark_logo`), CSS shows one.
- Two inline SVGs for the toggle icon (`.fa_light_icon`, `.fa_dark_icon`), same trick.
- `toggleColorMode()` flips a class on a root element; the token block does the rest.

**We already have this in Vue** —
[ThemeSwitcher.vue](../../../resources/admin/components/ThemeSwitcher.vue) plus
`darkModeMixin`-style persistence. Keep the Vue version; adopt the two-asset pattern for the
logo so the brand mark inverts with the theme.

Remember dark mode is gated by a setting in v1 — when it is off, the toggle should not
render at all. See [screens/settings.md](../phase/v1/screens/settings.md).

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

1. `variables.scss` with our nine tokens, light + dark.
2. `navigation.scss` ported from affiliate, class names re-prefixed.
3. Fix the three topbar defects — brand name, dashboard link, control order.
4. `PageLoadingSkeleton`, `ActionPanel`, `FilterGroup` — needed by every screen after.
