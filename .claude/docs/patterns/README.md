# Coding patterns

How we lay code out and why. Derived from reading the shipping plugins, in this order of
authority:

1. **fluent-community (fcom)** — first priority. When siblings disagree, fcom wins.
2. **fluent-affiliate** — second.
3. **fluent-booking** — third.

Where our scaffold is *newer* than all three, ours wins. Those cases are called out
explicitly; there are only a few, and copying the old shape into a new plugin would be a
regression.

| File | Covers |
|---|---|
| [backend.md](backend.md) | Directory map, controllers, services, models, policies, routes |
| [hooks-and-filters.md](hooks-and-filters.md) | Naming, self-registering handlers, when to emit, response filters |
| [frontend.md](frontend.md) | Bits / Pieces / Modules, the underscore convention, when to extract, modals |
| [admin-layout.md](admin-layout.md) | The wp-admin shell — navbar structure, design tokens, stylesheet split, dark mode |
| [database.md](database.md) | Migrators — and why we do not copy fcom's here |

---

## The two rules that govern everything else

### Readability beats brevity

Fewer lines is not the goal. Code that a stranger can follow on the first read is. fcom is
verbose on purpose — controllers extract and sanitize each field on its own line rather
than looping cleverly, and models list every default setting with a comment naming the
allowed values. Follow that.

Concretely: prefer an early return to a nested ternary, a named variable to a long
expression inlined twice, and an explicit `if` to a clever `??=` chain. If a reviewer has
to pause, it was too clever.

### Do not extract until the third use

**A component used once is not a component — it is a section of the page.** Two uses is
usually still fine inline. At three, extract.

Premature extraction costs more than duplication does: it invents a props contract before
anyone knows what varies, and the second caller then bends the component out of shape.
fcom's feature modules are full of large single-purpose files for exactly this reason.

The exception is genuine cross-feature UI — an avatar, a slug input, a drawer. Those go in
`Pieces/` from the start because the reuse is not speculative.

---

## What our scaffold already does better

Do not copy the sibling approach for these:

| Thing | Siblings | Ours |
|---|---|---|
| Nav / screen registration | PHP `add_submenu_page` per screen (all three) | server-declared in [vue.php](../../../app/Http/Routes/vue.php) via `$router->menu()` |
| Migrations | hand-written `CREATE TABLE` strings behind `SHOW TABLES LIKE` | Blueprint API with idempotent guards — see [database.md](database.md) |

---

## Conventions fixed for this plugin

| Thing | Value |
|---|---|
| Namespace | `FluentEvent\` |
| Text domain | `fluent-event` |
| REST | `fluent-event/v2` |
| Hook prefix | `fluent_event/` |
| Tables | `wp_fe_*` |
| CPT / taxonomies | `fe_event`, `fevent_*` |
| Vue | Options API only |

The enforced subset lives in [.claude/rules/](../../rules/) and is checked by the
PostToolUse scanner. This folder is the *shape* of the code; the rules folder is what will
block a commit.
