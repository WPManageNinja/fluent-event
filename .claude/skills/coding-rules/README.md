# coding-rules

Write-time guardrails so `/feature-review` audits pass on first run.

## What it is

A Claude Code skill that fires automatically when Claude is about to edit FluentCommunity source code. It loads the subset of rules relevant to the touched files, applies them while coding, runs self-audits per edit batch, and is backed by a PostToolUse hook that catches blatant anti-patterns the moment they hit disk.

Same scope-gating, severity calibration, and fix-pattern table as `/feature-review` — but applied **before** the audit instead of after. Goal: no rework loop.

## Why

The repo already has `/feature-review`: a multi-agent audit run pre-push that spawns 18 specialists + verifier + sweeps and writes findings to `.agent/reports/audit_<branch>.md`. Useful, but expensive to repeat. If Claude wrote audit-compliant code from the start, the audit becomes a confirmation step, not a rework gate.

This skill mirrors `/feature-review` at write time.

## When it fires

Auto-triggers when the planned work touches any of:

- `app/**` (PHP backend)
- `Modules/**` (free module trees — Auth, Course, Gutenberg, Integrations, Migrations, PushNotification, Theming)
- `database/Migrations/**`
- `app/Hooks/actions.php`, `app/Hooks/filters.php` (hook registration files), `app/Hooks/Handlers/**`, `app/Hooks/Scheduler.php`
- `boot/app.php` (activation/deactivation hooks)
- `src/**` (Vue 3 SPA)
- `gutenberg/**`, `custom-editor/**` (React/Gutenberg blocks)
- `package.json`, `composer.json`, `package-lock.json`, `pnpm-lock.yaml`, `composer.lock`
- `vite.unified.config.js`, `build.sh`, `deploy.sh`
- `.github/workflows/**`
- Mailer / notification handlers
- Tests (`tests/**`, `src/__tests__/**`)
- Sibling pro repo at `../fluent-community-pro/**` for any of the above

**Pro path mirroring (explicit rule).** Every scope-gate row that names a free-relative path also matches the same path under `../fluent-community-pro/`. Concrete pro paths spelled out in scope-gate rows are additive, not overriding.

Applies to ALL code edits — features, bug fixes, refactors. Not feature-only.

Does NOT fire for: prose-only `.md` edits (outside `CLAUDE.md`), audit reports, `builds/**`, `node_modules/**`, `vendor/**`.

## Lifecycle

```
user task
   │
   ▼
[skill auto-trigger]              ◄── description matches planned files
   │
   ▼
[scope-gate.md — path match]      ◄── picks rulesets for matched file paths
   │                                   (inline, fast)
   ▼
[content-grep on planned diff]    ◄── adds rulesets for behaviors (wp_mail,
   │                                   wp_remote_*, switch_to_blog, hash_hmac,
   │                                   $fillable change, do_action, etc.)
   │                                   (inline, fast)
   ▼
Big or unfamiliar task?
   ├─ YES ──> [pre-flight sub-agent]    ◄── spawn with pre-flight.md prompt
   │              │                         returns per-task checklist
   │              ▼                         (own content-grep + ruleset reads
   │         checklist in hand                + "Surface to user" prompts)
   │              │
   └─ NO ─────────┤
                  ▼
   ┌────────────────────────────────────────────┐
   │  edit batch                                │
   │     │                                      │
   │     ▼                                      │
   │  Edit / Write tool fires                   │
   │     │                                      │
   │     ▼                                      │
   │  [PostToolUse hook — coding-rules-scan.sh] │ ◄── hard gate
   │     │  (15 patterns, diff-aware)           │     exit 2 → Claude
   │     │                                      │     re-prompted →
   │     │                                      │     self-corrects → re-edit
   │     ▼                                      │
   │  (more edits in this batch)                │
   │     │                                      │
   │     ▼                                      │
   │  Meaningful batch boundary?                │
   │  (controller method done, model+migration  │
   │   pair, Vue component, ≥3 files, etc.)     │
   │     │                                      │
   │     ▼                                      │
   │  [self-audit sub-agent]                    │ ◄── spawn with self-audit.md
   │     │  re-runs content-grep on diff,       │     iterate up to 3x until
   │     │  walks rulesets, returns findings    │     NO ISSUES; fix silently
   │     │                                      │
   └─────┼──────────────────────────────────────┘
         │
         ▼  (more batches)
         │
   Task wrapping up?
         │
         ▼
[final self-audit sub-agent]      ◄── full task diff + final content-grep
   │                                   catches whatever per-batch missed
   ▼
user commits + pushes
   │
   ▼
[pre-push-feature-review.sh]      ◄── existing PreToolUse hook on `git push`
   │
   ▼
[/feature-review audit]           ◄── 18-agent audit; should pass first run
```

### Pre-flight is conditional

The pre-flight sub-agent spawns only for big / unfamiliar work — e.g. new module, new REST surface, new pro module, multi-file refactor crossing repo boundary, payment / OAuth / webhook handler, or anything matching 4+ rulesets. Small/medium tasks skip pre-flight and go straight to coding (steps 1+2 still run inline). See `pre-flight.md` "When to use" / "When NOT to use".

### Per-batch self-audit is heuristic

Parent Claude decides when to spawn — typical triggers: finished a controller method (~50+ lines), finished a model + matching migration, finished a Vue component, finished a Pinia store action, finished a module bootstrap, ≥ 3 files touched in this batch. Final self-audit always runs before declaring done. See `self-audit.md`.

### Hook re-edit loop is automatic

PostToolUse hook firing exit 2 feeds stderr back to Claude → Claude reads the violation list → re-edits the file → hook re-fires → continues until hook passes (exit 0). No human in the loop.

## File layout

```
.claude/skills/coding-rules/
├── SKILL.md                  # frontmatter trigger + lifecycle instructions
├── scope-gate.md             # file-pattern → ruleset map
├── pre-flight.md             # sub-agent prompt for big-task checklist
├── self-audit.md             # sub-agent prompt for per-batch review
├── README.md                 # this file
└── rulesets/
    ├── always.md             # universal (SQL, $_REQUEST, XSS, eval, crypto, role-based auth)
    ├── backwards-compat.md   # backwards-compat / deprecation / plugin constants
    ├── routes.md             # REST routes + 6 AJAX surfaces
    ├── policies.md           # role-based (Helper + User helpers, NOT capabilities)
    ├── models-migrations.md  # 21 models, 13 migrators, fcom_* tables, multi-type discipline
    ├── controllers-services.md
    ├── vue.md                # Options API only, $t mixin, no DOMPurify
    ├── modules.md            # Modules/ structure + FeaturesHandler + conditional loading
    ├── hooks.md              # 2 cadences, slash convention dominant
    ├── pinia-stores.md       # cache TTL + LRU + persistedstate + ticker
    ├── multisite.md
    ├── email.md              # EmailNotificationHandler chunked async
    ├── outbound-http.md      # SSRF, OAuth, wp_safe_remote vs wp_remote_request
    ├── integrations.md       # Pro Webhooks / CloudStorage / Paymattic
    ├── deps-build.md         # Vite (NOT webpack) + dual lockfiles
    ├── a11y-i18n.md          # $t() mixin, fluentComAdmin global
    └── tests.md              # Vitest + PHPUnit + Playwright
```

Backed by `.claude/hooks/coding-rules-scan.sh` (PostToolUse safety net, registered in `.claude/settings.json`).

## What gets enforced

Each ruleset is a tight checklist. Examples:

| Ruleset | Sample rules |
|---------|--------------|
| `always.md` | No `$_REQUEST` wholesale; explicit `$fillable`; role-based auth via `Helper::isSiteAdmin` / `Helper::canAccessPortal`; `esc_html__` not `__`; ban `eval`; `hash_equals` not `===` on signatures |
| `routes.md` | Policy method must exist for destructive controllers (else falls back to weak `verifyRequest`); param validators (`->int`, `->alphaNumDash`); 6 AJAX surfaces inventoried |
| `policies.md` | BasePolicy chain; `Helper::isSiteAdmin` / `User::hasCommunityAdminAccess` / `verifySpacePermission` reuse; URL-bound slug ≠ `$request->get('space_id')` (known SpacePolicy concern) |
| `models-migrations.md` | `$fillable` matches columns; multi-type tables (`fcom_spaces`, `fcom_notification_users`, `fcom_meta`) need `addGlobalScope`; `dbDelta` idempotency |
| `controllers-services.md` | No `$wpdb` in controllers; `$this->validate(...)`; `sendSuccess`/`sendError`; chunked async via Action Scheduler |
| `vue.md` | Options API ONLY; `v-html` justified + sanitized server-side; `$get`/`$post` mixin not new HTTP client; `FullEditorFrame` postMessage origin check |
| `modules.md` | Conditional module loading; no hook registration in `__construct`; pro inline `dbDelta` pattern |
| `hooks.md` | 2 real cadences (`fluent_community_scheduled_hour_jobs`, `fluent_community_daily_jobs`); slash convention; group `'fluent-community'` |
| `pinia-stores.md` | Stores use `this.$api`; cache TTL via `cacheStore`; ticker as single global poller; selective persistence; no PII in localStorage |
| `multisite.md` | `switch_to_blog`/`restore_current_blog` pairing; `site_option` vs `option` |
| `email.md` | Chunked async via Action Scheduler; CR/LF strip on headers; server-derived recipients; `wp_kses_post` at render |
| `outbound-http.md` | SSRF private-IP rejection; timeout + redirect bounds; `wp_safe_remote_*` preferred; CloudStorage `wp_remote_request` callout |
| `integrations.md` | Pro Webhooks: HMAC body sig (not just shared key); CloudStorage scheme validation; Paymattic idempotency; OAuth `state` |
| `deps-build.md` | Vite (NOT webpack); dual lockfiles kept in sync; SRI on CDN; `FLUENT_COMMUNITY_PLUGIN_VERSION` not `time()` for asset version |
| `a11y-i18n.md` | All strings via `$t()` mixin; text domain `fluent-community`; escape-after-translate; `$_n` for plurals |
| `backwards-compat.md` | `apply_filters_deprecated` for renamed hooks; down-migrations; plugin-constant semver; pro/free version-pair coordination |
| `tests.md` | Don't delete tests silently; new code in tested dirs gets tests; Vitest pattern; PHPUnit pattern |

Full text in each ruleset file. Each rule cites the matching row in `.claude/feature-review/fix-patterns.md`.

## PostToolUse safety net

`.claude/hooks/coding-rules-scan.sh` runs on every `Edit` / `Write` / `MultiEdit` / `NotebookEdit` of in-scope source files. Greps for hard-block patterns:

1. Wholesale superglobal assignment (`$data = $_POST;`)
2. `->fill($request->all())`
3. `Model::create($_POST / $request->all())`
4. `permission_callback => '__return_true'`
5. Raw `unserialize()` OR `maybe_unserialize()` (only `Utility::safeUnserialize` is sanctioned)
6. `eval()` / `new Function()`
7. `v-html` in `.vue` files
8. `<script setup>` (Composition API banned per CLAUDE.md rule 1)
9. `wp_remote_*` without `timeout` in file
10. `mt_rand` / `uniqid` alongside token/secret/nonce variable names
11. `hash_hmac` without `hash_equals` in file
12. `switch_to_blog` without `restore_current_blog` in file
13. Email header build (`Reply-To: …`) without CR/LF strip
14. `time()` as asset enqueue version (defeats cache-bust)
15. `postMessage(target, '*')` — leaks message to any frame

**Diff-aware**: scans only lines INTRODUCED by the current edit (`git diff` vs HEAD). Existing legacy callsites with `phpcs:ignore` annotations are not re-flagged on unrelated edits. Patterns that require whole-file context (e.g., `hash_hmac` without `hash_equals` anywhere in the file) still scan the full file.

Violation → exit 2 → stderr fed back to Claude → Claude re-prompts and self-corrects.

**Opt-outs**:
- Per line: add `phpcs:ignore` or `coding-rules-scan:ignore` on the same line
- Per file: add a comment containing `coding-rules-scan: off`
- Per session: `export CODING_RULES_SCAN_OFF=1`

## Self-audit (per batch + final)

Spawned as a `general-purpose` sub-agent with `self-audit.md` as the prompt. Inputs: diff scope, changed files, matched rulesets. Returns `NO ISSUES` or findings using the schema from `.claude/feature-review/schema.md` — same severity calibration as the real audit.

Trigger heuristics (Claude judgment):
- Finished controller method (~50+ lines)
- Finished model + matching migration
- Finished Vue component
- Finished Pinia store action
- Finished module bootstrap
- ≥ 3 files touched in this batch
- Always at end of task before declaring done

Findings get fixed silently. User sees them only if design judgment is required.

## Maintenance

**Drift is the failure mode.** Two contracts to keep synced:

1. `scope-gate.md` here ↔ scope-gating table in `.claude/commands/feature-review.md`
2. Each `rulesets/*.md` ↔ matching `.claude/feature-review/agents/NN-*.md` brief

When `/feature-review` evolves, mirror the change here. When you add a new rule here, add it to the corresponding feature-review agent brief if it should also surface in the audit.

## Adding a new rule

1. Identify which ruleset it belongs to (or whether a new one is needed)
2. Add the rule with a one-line "why" and the concrete fix pattern
3. Cite the matching row in `.claude/feature-review/fix-patterns.md` (add a new row there if needed)
4. Append a self-audit checklist item at the bottom of the ruleset
5. If the rule has a regex-detectable hard-block pattern, add a check in `.claude/hooks/coding-rules-scan.sh`
6. Mirror to the corresponding `feature-review/agents/NN-*.md` brief so the audit also catches it

## Adding a new ruleset

1. Create `rulesets/<name>.md` following the existing pattern (intro → rule sections → self-audit checklist at bottom)
2. Add a row to `scope-gate.md` mapping a file pattern to the new ruleset
3. Update the registry table in `SKILL.md`
4. Update this README's file-layout section
5. Mirror to feature-review's scope-gating table

## Relationship to other workflows

- `/feature-review` — the audit this skill is calibrated against
- `pre-push-feature-review.sh` — PreToolUse hook that runs `/feature-review` on `git push`
- `coding-rules-scan.sh` — PostToolUse hook owned by this skill
- `CLAUDE.md` — repo-level conventions (12 numbered rules); ruleset content respects everything documented there
- `.claude/skills/coding-patterns.md` — codified canonical examples (controller, policy, Vue Options API); ruleset rules align with these patterns

## Soft enforcement

This is not a gate. Claude self-corrects on hook hits and self-audit findings rather than blocking the user. The pre-push audit remains the hard checkpoint. The skill's job is to make the audit boring.

If the skill's catching nothing and audits stay clean, it's working. If audits keep flagging the same archetype, the matching ruleset (and possibly the hook) needs the rule added.
