# vue.md — Vue 3 SPA (Options API)

Triggered when editing `src/**`. Pair with `a11y-i18n.md` and `pinia-stores.md`.

## Vue API discipline (per CLAUDE.md rule 1)

**Options API only.** 292 of 296 `.vue` files use `export default { ... }`. The single `<script setup>` callsite (`src/Modules/Common/MilkDownEditor/Tooltip.vue`) is an outlier — don't expand it.

Conventions:
- `data() { return {...} }` for component state (not `ref()` / `reactive()`)
- `computed: {...}` for derived state
- `methods: {...}` for actions
- `watch: {...}` for reactive side effects
- `mounted()` / `beforeUnmount()` for lifecycle

Don't import `defineComponent` for plain components (only needed for TS narrowing). Don't introduce Composition API helpers (`ref`, `reactive`, `computed`, `watch` from `vue` directly inside `<script setup>`) — breaks the codebase-wide pattern.

## XSS

- `v-html` is the FIRST suspect. Default to `v-text` or `{{ }}` or the `FeedMdContent` component (renders Markdown safely per CLAUDE.md rule 8).
- Free has **76 existing `v-html` callsites across 52 `.vue` files** (binding things like `field.help`, `slot.description`, formatted strings, SVG content). Adding a NEW one needs explicit justification:
  - Why does the data require HTML rendering?
  - Is the input trusted (server-supplied admin setting) or user-influenced?
  - For user-influenced markdown: use `FeedMdContent` (`src/Modules/Common/FeedMdContent.vue` or equivalent) which renders Markdown safely.
  - For user-influenced raw HTML: sanitize at the API boundary in PHP via `wp_kses_post` BEFORE the data is exposed.
- **DOMPurify is NOT installed** (`package.json` has no `dompurify`). Don't suggest `import DOMPurify` without adding the dep + bundle plumbing. Server-side `wp_kses_post` is the existing pattern.
- Fix-pattern row: `XSS (Vue)`.

## `:href` / `:src` with user data

- Never bind a `javascript:` URL. Filter the protocol before assignment:

```js
const safeHref = computed(() => /^(https?:|mailto:|tel:)/.test(this.url) ? this.url : '#');
```

- Member-provided links (profile bio, post links) must be filtered server-side too.

## Listener cleanup

- Every `addEventListener` registered in `mounted` has a matching `removeEventListener` in `beforeUnmount`. Fix-pattern row: `Listener cleanup`.
- **Current state** (excluding vendored `libs/`): 42 `addEventListener` vs 26 `removeEventListener` in `src/` — leak gap of ~16. Several listeners are not cleaned up.
- Sample correct pattern: `Modules/FullEditorFrame.vue:81` adds + `:85` removes.

## ⚠ Known `postMessage` finding

`src/Modules/FullEditorFrame.vue` has ONE `message` listener:
- `:81` — `window.addEventListener('message', this.handleEditorMessage)`
- `:85` — paired `removeEventListener` (good)
- `:101` — stores `editorOrigin` from the editor iframe URL
- `:146-166` — `handleEditorMessage(event)` destructures `event.data` and dispatches by `action` WITHOUT consulting `event.origin` or `editorOrigin`. **Real bug.** When editing this file, add origin check:

```js
handleEditorMessage(event) {
    if (event.origin !== this.editorOrigin) return;
    const { action, data } = event.data;
    // ...
}
```

- Outgoing `postMessage` calls at L128, L140 use `'*'` as target origin. Should be `this.editorOrigin`.

Fix-pattern row: `postMessage origin`.

## Prototype pollution

- `Object.assign(target, JSON.parse(input))` or `_.merge(target, userInput)` without key filter = pollution.
- Filter `__proto__`, `constructor`, `prototype`:

```js
for (const k of Object.keys(input)) {
    if (['__proto__', 'constructor', 'prototype'].includes(k)) continue;
    target[k] = input[k];
}
```

## `eval` / `new Function`

- Banned. Free has zero callsites in `src/` (excluding vendored `libs/`) — keep it that way.

## REST calls

- Use `src/Bits/Rest.js` via the global mixin's `$get` / `$post` / `$put` / `$patch` / `$delete` (defined in `src/app.js:183-448`). Per CLAUDE.md rule 12: components use `$get`/`$post`, Pinia stores use `this.$api`.
- Don't introduce `axios` / `fetch` / `XMLHttpRequest` ad-hoc — `Rest.js` already wraps XHR, adds `X-WP-Nonce`, shims method override (`X-HTTP-Method-Override`), and handles nonce-renewal events.
- **No `AbortController` is plumbed currently** (`Rest.js` uses raw XHR). Don't introduce `AbortController` in a single component while the rest of the app uses XHR — if needed, do it in `Rest.js` so the pattern propagates.
- `query_timestamp` cache-buster is added automatically.

## Optimistic UI

- Pinia stores already support optimistic updates (`feed.js`, `user.js` — follow/unfollow). On REST error, the store reverts. Reuse store actions; don't duplicate optimistic flows in components.

## Submit guards

- Disabled state on submit button while in-flight. Loading spinner. Prevents double-submit.
- Don't rely on the backend to dedupe a race — guard the UI.

## Error handling

- Every REST failure surfaces to the user (toast / inline error). Silent failure = bad UX.
- Element Plus `ElMessage` or the global `$_handleError` mixin method is the standard.

## Pagination

- Param names match backend exactly (`page`, `per_page`, `cursor`). Drift = broken pagination.

## i18n

- Use the `$t('English text')` mixin defined in `src/app.js:233-260`. Reads from `window.fluentComAdmin.i18n[string]`. Supports `%s` / `%d` / `%1$s` placeholders.
- For plurals: `$_n('1 post', '%d posts', $count)` (mixin at `app.js:261-268`).
- NOT `__()`, NOT `vue-i18n`. See `a11y-i18n.md`.

## Vite aliases (per CLAUDE.md rule 9)

- `@` → `src/`
- `~element-plus` → element-plus package
- `~milkdown` → milkdown package

Don't introduce new aliases without coordinating with `vite.unified.config.js`.

## Theming / dark mode

Portal toggles `<html class="dark">` via `src/portal_general.js` (state in `localStorage 'fcom_color_mode'`). Tokens defined in `src/global.scss` + `src/theme-default.scss` under `--fcom-*` prefix (e.g. `--fcom-primary`, `--fcom-text-link`, `--fcom-menu-bg-hover`).

Rules:
- No hardcoded colors in `<style>` / SCSS. Use `var(--fcom-*)`. New color needed = add token to theme files with both mode values.
- SVG icons use `fill="currentColor"` / `stroke="currentColor"` (existing pattern — see `Pieces/Icons/BookmarkIcon.vue`).
- Verify both modes before declaring done (toggle via portal header or `document.documentElement.classList.toggle('dark')` in devtools).

Self-audit:
1. No hardcoded color literals in `<style>`?
2. SVG icons use `currentColor`?
3. Verified in both light + dark mode?

## Two SPAs

Per CLAUDE.md rule 10: Portal (`src/app.js` → `/portal`) and Admin (`src/admin_app.js` → `/wp-admin/...`). Only portal has code splitting (per the `manifest` in `config/app.php`).

When adding a feature, decide which SPA it belongs to:
- Portal: feed, spaces, member profile, comments — anything users see while in the community
- Admin: settings, moderator tools, integrations config, license

Don't import portal-only modules into the admin entry (or vice versa) — bloats the wrong bundle.

## Component placement

- Shared components → `src/Modules/Common/`
- Feature components → `src/Modules/<Feature>/`
- Pieces (small icons, badges) → `src/Pieces/`

Don't create top-level component files outside these directories.

## Self-audit

For each Vue change:
1. Options API (not `<script setup>`)?
2. New `v-html`? Justified + sanitized SERVER-SIDE (or uses `FeedMdContent`)?
3. Listeners cleaned up on `beforeUnmount`?
4. No `axios` / `fetch` / `XMLHttpRequest` — uses `$get`/`$post` mixin?
5. Submit button disables during request?
6. Error toast on every REST failure path?
7. No prototype-pollution-prone merges?
8. No `:href` / `:src` accepting `javascript:`?
9. New `addEventListener('message', ...)` has `event.origin` check?
10. `postMessage` outgoing target is specific origin (not `'*'`)?
11. Strings use `$t()` mixin (no raw English text in templates)?
12. Component placed in `src/Modules/<X>/` or `src/Pieces/`?
