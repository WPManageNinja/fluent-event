# bem — CSS class naming

Applies to every class we author — SCSS in `resources/scss/`, markup in `.vue` templates and
`app/Views/**/*.php`.

Styling *location* is [vue.md](vue.md) (SCSS files, not components). This file is about what
the class is *called*.

## The shape

```
fe-block__element--modifier
```

- **`fe-` prefix on every block.** wp-admin is a shared document — an unprefixed `.navbar`
  or `.card` collides with core, another plugin, or the active theme.
- **`__` marks an element**, `--` marks a modifier. Single hyphen joins words inside any one
  part: `fe-navbar__link-text`, not `fe-navbar__linkText` or `fe-navbar__link_text`.
- **Lowercase, kebab inside parts.** No camelCase, no PascalCase.

```html
<div class="fe-navbar__link-wrapper" data-key="events">
    <a class="fe-navbar__link fe-navbar__link--active">
        <span class="fe-navbar__link-text">Events</span>
    </a>
</div>
```

## No element of an element

`fe-navbar__links__link` is wrong however deep the DOM goes. BEM elements are flat — the
block is the only owner.

```
BAD   .fe-navbar__settings__link
GOOD  .fe-navbar__link              (reused wherever the navbar needs a link)
```

If flattening produces a name that no longer reads as part of the block, that is the signal
the inner thing is its own block, not an element.

## A modifier never stands alone

`--active`, `--compact`, `--danger` change a base; they never appear without it. In markup
that means both classes; in SCSS it means writing the modifier at the base's specificity:

```scss
.fe-navbar__link {
    color: var(--fe-secondary-text, #525866);

    &:hover { background-color: var(--fe-active-bg, #f0f3f5); }   // 0,2,0

    // LOSES — `&--active` compiles to 0,1,0 and `:hover` outranks it.
    // The symptom is a rule that looks right and does nothing, which
    // invites an `!important` that hides the real problem.
    &--active { color: var(--fe-primary-text, #19283a); }

    // WINS — 0,2,0, ties `:hover` and comes later in source order.
    &.fe-navbar__link--active { color: var(--fe-primary-text, #19283a); }
}
```

Repeat the full class rather than reaching for `!important`. Same discipline as the Element
Plus rule in [vue.md](vue.md), for the same reason.

## Nesting

`&__` nesting is the house style — it keeps a block's rules in one place:

```scss
.fe-navbar {
    &__logo { … }
    &__links { … }
    &__link { … }
}
```

The cost is real: `grep fe-navbar__link` will not find `&__link`. Accepted, because the block
name sits a few lines above. **Grep for the block, not the element.**

Keep it to one level of `&__`. A second level means an element of an element, which the rule
above already bans.

## Two vocabularies, on purpose

Ported from fluent-affiliate, and worth keeping straight:

| Form | Used for | Examples |
|---|---|---|
| `fe-block__element` | structural styling of a component | `fe-navbar__link`, `fe-navbar__logo-img` |
| `fe_snake_case` | shell wrappers and theme/state swap hooks | `fe_wrapper`, `fe_body`, `fe_route_wrapper`, `fe_light_logo`, `fe_dark_icon`, `fe_color_mode` |

Do not invent a third. New component styling is BEM. Reach for `fe_snake_case` only when
matching an existing suite-wide hook — the light/dark asset pair being the live example.

## JS and test hooks are attributes, not classes

A class is for styling; a hook that JavaScript or a test queries is `data-*`. The active-link
mechanism is the pattern: `data-key="events"` on the wrapper, matched against the route's
`meta.active_menu`.

```js
jQuery(`.fe-navbar__link-wrapper[data-key="${activeKey}"] .fe-navbar__link`)
```

`data-key` survives a translated label and a restyle; a class does neither.

## Utilities and vendor

- **Utilities** are single-purpose and blockless — `.capitalize`. Keep them few, and in
  `globals.scss`.
- **Element Plus classes are not ours.** Never rename or re-prefix `.el-*`. Overrides live in
  `vendor.scss`, scoped under `.toplevel_page_fluent-event` so they cannot escape our screen.

## Self-audit

1. Every block prefixed `fe-`?
2. `__` for elements, `--` for modifiers, single hyphen inside a part, all lowercase?
3. No element-of-an-element (`__a__b`)?
4. Every modifier written at the base class's specificity — no `!important` standing in for it?
5. Nesting one `&__` deep at most?
6. New styling is BEM; `fe_snake_case` only to match an existing suite hook?
7. JS/test hooks are `data-*`, not classes?
8. No `.el-*` renamed; vendor overrides scoped in `vendor.scss`?

**Not hook-enforced** — self-audit discipline (the scanner has no naming rule).
