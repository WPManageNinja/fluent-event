# Frontend patterns

Reference: `fluent-community/src/`. See [README.md](README.md) for priority order.

---

## Three places code can live

fcom splits its Vue source three ways, and the split is worth copying exactly:

| Directory | Holds | fcom examples |
|---|---|---|
| `Bits/` | **Non-visual.** Mixins, REST wrapper, storage, validators, event bus | `Rest.js`, `Storage.js`, `validator.js`, `event-bus.js`, `darkModeMixin.js` |
| `Pieces/` | **Cross-feature UI.** Used by three or more modules | `UserAvatar.vue`, `Drawer.vue`, `ImageUploader.vue`, `SlugInput.vue`, `ActionPopover.vue`, `NotFoundResult.vue` |
| `Modules/<Feature>/` | **Feature screens** and their private parts | `Modules/Space/`, `Modules/Members/`, `Modules/Notifications/` |

Our scaffold's equivalents already exist: `resources/admin/components/` is `Pieces`,
`resources/admin/mixins/` + `utils/` are `Bits`, and `resources/admin/modules/` is
`Modules`. **Lowercase `modules/`** — the vite glob is
`resources/admin/modules/**/*.vue`, and affiliate's capital-M `Modules/` will silently fail
to resolve.

Ratio check: fcom has **23 Pieces against 15 feature modules**. Shared UI stays a small,
carefully-chosen set — not a dumping ground.

---

## The underscore convention

fcom and booking both use filename prefixes to encode visibility. This is the single
highest-value readability convention in the codebase:

```
Modules/Space/
├── AllCommunities.vue      ← no prefix: a routable screen
├── AboutSpace.vue          ← no prefix: a routable screen
├── _SpaceHeader.vue        ← one underscore: private to this module
├── _AddMemberForm.vue      ← one underscore: private to this module
└── __SpaceMenuItems.vue    ← two underscores: a part of a part
```

You can tell what a file is, and whether you may reuse it, from the file listing alone. No
import graph needed.

Booking uses a `parts/` subdirectory for the same purpose (`Calendars/parts/`,
`Schedules/parts/`). **Prefer fcom's underscores** — the prefix survives being moved and
sorts private files next to the screen that owns them.

---

## When to extract a component

**Not before the third use.** See [README.md](README.md); it is the rule most often broken.

A single-use "component" is a section of a page wearing a costume. It adds a props
contract, an import, a file to open, and an indirection — in exchange for nothing. fcom's
feature modules are full of large single-purpose `.vue` files precisely because the authors
did not extract speculatively.

Extract early only for genuine cross-feature UI — an avatar, a drawer, a slug input. Those
go in `Pieces/` from day one because the reuse is not speculative.

When you do extract, the signal you were right is that the props contract is obvious. If
you are threading six props and two emits to make it fit the second caller, it wanted to
stay inline.

---

## Modals

Plain `el-dialog`, defined inside the feature module that owns it. No modal manager, no
global registry.

- Named `*Modal.vue` — `ReportContentModal.vue`, `AudioEditModal.vue`,
  `_SpaceLinkEditModal.vue`.
- Private to one module ⇒ underscore prefix. Shared across modules ⇒ it moves to
  `Modules/Common/`, not to `Pieces/`. `Pieces` is for primitives, `Common` for shared
  feature UI — fcom keeps `PreviewPostModal.vue` and `ReportContentModal.vue` there.
- Visibility is a `v-model` boolean owned by the **parent**. The modal does not decide when
  it is open.
- For side panels rather than centre-screen dialogs, fcom has a `Pieces/Drawer.vue`
  wrapping `el-drawer`. Use a drawer for anything with a form long enough to scroll.

Accessibility is not optional here: focus moves into the modal on open and returns to the
trigger on close, Escape closes it. See [a11y-i18n.md](../../rules/a11y-i18n.md).

---

## REST calls

Always through the app's wrapper — `$get` / `$post` / `$put` / `$delete` mixins, or
`this.$api` in stores. It supplies `X-WP-Nonce`, method override, and nonce renewal.

Never introduce `axios` or bare `fetch` in a single component. fcom centralises this in
`Bits/Rest.js` for exactly this reason.

Two UX guards, every time:

- Disable the submit button while a request is in flight — otherwise double-submit creates
  two rows.
- Surface every failure. A silent `.catch()` is a bug report you will receive later with no
  information in it.

---

## Strings and colour

- Every user-facing string through `$t()`. Never a hardcoded English literal in a template
  — fcom passes even placeholder text through it (`:placeholder="$t('Select User')"`).
- Never `v-html` on a translated string; a malicious translation becomes render-time XSS.
- No colour literals in `<style>`. Theme CSS variables only, legible in light and dark.
  SVG icons use `fill="currentColor"`.

Full detail: [vue.md](../../rules/vue.md), [a11y-i18n.md](../../rules/a11y-i18n.md).
