# deps-build.md — dependencies + build wiring

Triggered when editing `package.json`, `composer.json`, `*-lock.*`, `vite.unified.config.js`, `build.sh`, `deploy.sh`, `.github/workflows/**`, `gutenberg/` build config, or `custom-editor/` build config.

## Build system

FluentCommunity uses **Vite** (not Laravel Mix / webpack like FluentBooking).

Per CLAUDE.md:
- `npm install` — install JS deps
- `npm run dev` — Vite dev server (HTTPS, port 4444) with HMR
- `npm run build` — production build (main app only)
- `npm run prod` — Build all: main + Gutenberg + custom-editor + SCSS
- `npm run build:gutenberg` — Gutenberg blocks only (uses `@wordpress/scripts`)
- `npm run build:custom-editor` + `npm run build:custom-editor-scss` — Lesson editor
- `composer install` — PHP deps (WPFluent framework, Action Scheduler)

Config: `vite.unified.config.js` (NOT `vite.config.js`). The "unified" config drives multiple entry points; the `manifest` in `config/app.php` is generated from this — keep them in sync.

Pro uses **Laravel Mix 6** (webpack wrapper) — different system from free's Vite. Build config: `../fluent-community-pro/webpack.mix.js`. Output to `../fluent-community-pro/assets/`. Pro frontend is intentionally minimal — only `src/Onboarding.vue` + `src/app.js` + `src/app.scss` ship. Pro `resources/` directory is empty (legacy placeholder). Don't try to share build infra between repos — different bundlers, different aliases, different manifests.

Pro build commands (per pro CLAUDE.md):
- `composer install` — PHP deps
- `npx mix` — build
- `npx mix --watch` — watch mode

## Lockfile discipline (⚠ dual lockfile concern)

Free has BOTH `package-lock.json` AND `pnpm-lock.yaml`. This is a maintenance trap — two package managers can produce different dependency trees.

Rules:
- When updating deps, ALWAYS update both lockfiles in the same commit (run `npm install` then `pnpm install`), OR commit to using one. Treat divergence as a bug.
- Don't add a third package manager (bun, yarn).
- The team's `settings.json` allowlist includes `npm install`, `npm run *`, `bun --version`, `bun run prod`, `bun run build:bun:*` — bun is informally supported but lockfile is npm-managed.
- `composer.lock` for PHP — update with every `composer.json` change.

## Adding a dependency

- Verify the package is maintained: last release date, open vulnerabilities, weekly download count.
- Pin to a known version, not `^` / `~` ranges that auto-bump major or minor at install time. For dev deps, ranges are fine; for runtime, pin.
- Document why this dep is needed in the commit message.
- For Vue / Pinia / Element Plus / Milkdown ecosystem deps — match existing minor versions to avoid version-skew bugs.

## Removing a dependency

- Grep the codebase for every import of the removed package before deleting.
- Check both lockfiles to confirm transitive deps that ONLY this package depended on are also removed.

## Vite config

- `vite.unified.config.js` — driver for multiple entry points (portal app, admin app, onboarding, custom-editor, gutenberg, SCSS bundles).
- Aliases per CLAUDE.md rule 9: `@` → `src/`, `~element-plus` → element-plus, `~milkdown` → milkdown.
- Adding a new entry: add to `vite.unified.config.js` AND regenerate `config/app.php` manifest section. Don't hand-edit one without the other.

## Sub-builds

- **Gutenberg block** (`gutenberg/`) uses `@wordpress/scripts` — separate build, its own `node_modules`. Don't import portal Vue components from here.
- **Custom editor** (`custom-editor/`) is a Gutenberg sidebar plugin using `@wordpress/plugins` + `@wordpress/editor`. Separate build, its own `node_modules`. NOT Vue.

When editing these:
- Use `@wordpress/element` (React-compatible), NOT Vue.
- Read `window.fcomMetaSettings` for plugin data (NOT `window.fluentComAdmin` which is portal-only).
- Build separately via the matching npm script.

## SRI on CDN scripts

- Any new `<script src="https://cdn..."` MUST have `integrity="sha384-…" crossorigin="anonymous"`. Fix-pattern row: `SRI`.
- Or self-host the script. Self-hosting is preferred — survives CDN compromise.

## CI workflows

- `.github/workflows/**` change: verify the workflow YAML is valid.
- Secrets used in workflows declared in repo settings, not hardcoded.
- New build step that runs untrusted code (e.g., `npm install` on a fork PR) — gate with `if: github.event.pull_request.head.repo.full_name == github.repository`.

## Supply-chain hardening

- New dep maintained by a single person with infrequent releases = supply-chain risk. Document the decision.
- Avoid post-install scripts where possible (`npm install --ignore-scripts` in CI when safe).

## Asset enqueue dedup

- Register a library ONCE with a stable handle. Dependents declare it as a dependency, don't re-register.

```php
wp_register_script('fcom-some-lib', $url, [], FLUENT_COMMUNITY_PLUGIN_VERSION, true);
wp_enqueue_script('fcom-feature-x', $feature_url, ['fcom-some-lib'], FLUENT_COMMUNITY_PLUGIN_VERSION, true);
```

- Free + pro both enqueuing the same library under different handles = library loads twice. Pick one handle (in free), pro declares it as dependency.

## Cache-busting versions

- Use `FLUENT_COMMUNITY_PLUGIN_VERSION` constant for `$ver` arg of `wp_enqueue_*`. NEVER use `time()` (defeats browser cache — production performance hit).
- Bump `FLUENT_COMMUNITY_PLUGIN_VERSION` (in plugin header AND `define()` in `fluent-community.php`) when shipping asset changes (see `backwards-compat.md`).

## Build artifact location

- `builds/` is the release output. Don't commit `builds/` content (it's generated).
- `.gitignore` covers `builds/`, `node_modules/`, `vendor/`, lockfile backups.

## `deploy.sh`

Pre-deploy step alongside `build.sh`. If edits to `deploy.sh` change what's bundled into a release, document in the commit AND verify against the build manifest.

## Self-audit

For each deps/build change:
1. BOTH `package-lock.json` AND `pnpm-lock.yaml` updated alongside `package.json`?
2. New runtime dep pinned (not `^` / `~`)?
3. Removed dep — no orphan imports?
4. Vite config (`vite.unified.config.js`) + `config/app.php` manifest in sync?
5. New CDN script has SRI?
6. CI workflow YAML valid + secrets gated?
7. New shared library registered ONCE with a stable handle; free + pro don't both register the same lib?
8. Asset version uses `FLUENT_COMMUNITY_PLUGIN_VERSION` (not `time()`)?
