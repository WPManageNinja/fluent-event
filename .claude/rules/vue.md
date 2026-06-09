# vue — Vue 3 SPA (Options API)

Applies to all `.vue` / frontend `src/**` code.

## API discipline

**Options API only.** No `<script setup>` / Composition API.

- `data() { return {...} }` for state (not `ref()` / `reactive()`)
- `computed: {...}` derived state
- `methods: {...}` actions
- `watch: {...}` reactive side-effects
- `mounted()` / `beforeUnmount()` lifecycle

## XSS — `v-html`

- `v-html` is the first suspect. Default to `{{ }}` / `v-text`.
- A NEW `v-html` needs justification: is the data trusted (server admin setting) or
  user-influenced? User-influenced HTML/markdown must be sanitized **server-side**
  (`wp_kses_post` in PHP) BEFORE it reaches the API response — not in the browser.
- Don't reach for `DOMPurify` without adding the dep + bundle plumbing; server-side
  `wp_kses_post` is the pattern. See [fix-patterns.md](fix-patterns.md).

## `:href` / `:src` with user data

Never bind a `javascript:` URL. Filter the protocol:

```js
const safeHref = computed(() =>
    /^(https?:|mailto:|tel:)/.test(this.url) ? this.url : '#');
```

Member-provided links must also be filtered server-side.

## postMessage

- **Incoming**: every `addEventListener('message', ...)` checks `event.origin`
  against the expected origin before acting on `event.data`.
- **Outgoing**: never `postMessage(msg, '*')` — pass the specific target origin.

```js
handleMessage(event) {
    if (event.origin !== this.expectedOrigin) return;
    const { action, data } = event.data;
}
```

## Listener cleanup

Every `addEventListener` in `mounted` has a matching `removeEventListener` in
`beforeUnmount`. Unpaired listeners leak.

## Prototype pollution

`Object.assign(target, JSON.parse(input))` / `_.merge(target, userInput)` without a
key filter pollutes. Skip `__proto__`, `constructor`, `prototype`.

## `eval` / `new Function`

Banned. See [fix-patterns.md](fix-patterns.md).

## REST calls

- Use the app's REST wrapper (global `$get` / `$post` / `$put` / `$patch` / `$delete`
  mixin; stores use `this.$api`). It adds `X-WP-Nonce`, method-override, and nonce
  renewal.
- Don't introduce ad-hoc `axios` / `fetch` / `XMLHttpRequest` in a single component.
- Pagination param names match the backend exactly (`page`, `per_page`, `cursor`).

## UX guards

- Disable the submit button while a request is in-flight (prevents double-submit).
- Every REST failure surfaces to the user (toast / inline error) — no silent failure.

## i18n + theming

- Wrap UI strings in the `$t('English text')` mixin — not raw `__()` / `vue-i18n`.
- No hardcoded color literals in `<style>` / SCSS — use the theme CSS variables; SVG
  icons use `fill="currentColor"` / `stroke="currentColor"`.

## Self-audit

1. Options API (not `<script setup>`)?
2. New `v-html` justified + sanitized SERVER-SIDE?
3. Listeners cleaned up on `beforeUnmount`?
4. Uses the `$get`/`$post` wrapper, not ad-hoc fetch?
5. Submit button disables during request; error surfaced on failure?
6. No prototype-pollution-prone merges; no `:href`/`:src` accepting `javascript:`?
7. Incoming `message` listener checks `event.origin`; outgoing `postMessage` uses a specific origin?

**Enforced by** scanner rule 7 (`v-html`), rule 8 (`<script setup>`), rule 15 (`postMessage` with `'*'`).
