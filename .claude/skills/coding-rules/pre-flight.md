# pre-flight.md — sub-agent prompt for big tasks

Use this prompt when spawning a `general-purpose` sub-agent BEFORE editing on unusually large or unfamiliar work (new module, new REST surface, new pro module, multi-file refactor). For small targeted edits, skip the sub-agent — load rulesets directly.

## Prompt template

```
You are a pre-flight reviewer for FluentCommunity coding work. The parent Claude is about to edit code in this repository. Your job: read the relevant ruleset files, then produce a tight per-task checklist tied to the specific planned changes.

## Inputs (parent will pass these to you)

- Task description: <one-line summary of what's being implemented>
- Files planned for edit: <list>
- Matched rulesets so far (from scope-gate.md path-match): <list>
- Free repo path: /Users/macbook/Sites/fluent-booking/wp-content/plugins/fluent-community
- Pro repo path: ../fluent-community-pro (or "excluded — pro on different branch")

## What to do

1. Read `.claude/skills/coding-rules/scope-gate.md` and `.claude/skills/coding-rules/SKILL.md` (the content-grep keyword table is in SKILL.md step 2).

2. **Run content-grep** on every planned-edit file's CURRENT content + the planned diff (whatever the parent intends to add). For each keyword pattern from the SKILL.md table that hits, add the corresponding ruleset to the matched-set. Return the FINAL merged ruleset list before producing the checklist — call out which rulesets came from path-match vs content-grep so the parent sees the reasoning.

   Concretely, grep for:
   - `wp_mail\s*\(` / `Reply-To:` / `From:` / `Cc:` / `Bcc:` header literals / `EmailComposer` / `EmailNotificationHandler` → `email.md`
   - `wp_remote_(get|post|request|head)` / `wp_safe_remote_` / OAuth `state` handling → `outbound-http.md`
   - `switch_to_blog` / `restore_current_blog` / `is_multisite` / `(get|update|delete)_site_option` / `\$wpdb->base_prefix` / `wp_cache_(get|set|delete)` / `(get|set|delete)_transient` → `multisite.md`
   - `hash_hmac` near webhook / `Modules/Webhooks/**` / `Modules/CloudStorage/**` / `Modules/Integrations/**` / OAuth / `client_secret` / S3 / R2 / BunnyCDN driver edits / Paymattic → `integrations.md`
   - `do_action_deprecated` / `apply_filters_deprecated` / hook removal / route removal / migration `DROP COLUMN` / `$fillable` change / public CSS+DOM rename / `str_contains` / `enum` / `readonly` / `match\s*\(` / `?->` / `JSON_EXTRACT` / `GENERATED ALWAYS` / CTE / window function → `backwards-compat.md`
   - `->(create|update|fill|save|insert|delete)\(` or `\$wpdb->(insert|update|delete|replace|query)` in services / controllers / Modules/**/Controllers → `controllers-services.md` + `models-migrations.md`
   - New `WHERE` / `ORDER BY` / `JOIN` on an unindexed column → `models-migrations.md`
   - New `do_action\s*\(` or `apply_filters\s*\(` emission anywhere (not just `app/Hooks/**`) → `hooks.md`
   - `addGlobalScope` / `withoutGlobalScopes` / multi-type-table access (`fcom_spaces`, `fcom_notification_users`, `fcom_meta`) → `models-migrations.md` (multi-type discipline)
   - New `defineStore` / Pinia store config change / persistedstate change → `pinia-stores.md`
   - Test file edit / deletion / new feature without coverage → `tests.md`

3. Read every matched ruleset file under `.claude/skills/coding-rules/rulesets/`.
4. Read `.claude/feature-review/fix-patterns.md` for the fix-pattern row index.
5. Read CLAUDE.md to confirm convention assumptions (Options API only, hook prefix, REST X-HTTP-Method-Override, multi-type tables, etc.).
6. For unfamiliar code paths, glance at related existing code in the repo (don't deep-dive — bounded read).
7. Produce a CHECKLIST scoped to THIS task only:
   - Bullet per rule that applies to the planned changes
   - Cite the ruleset file + line range
   - Cite the matching fix-pattern row when applicable
   - Skip rules the task doesn't touch

## Output format

```
# Pre-flight checklist for: <task summary>

## Rulesets loaded
- Path-match: <list>
- Content-grep: <list with the keyword that triggered each>

## Must do
- [ ] <rule> — `<ruleset>.md:LL-LL` + fix-pattern row `<row name>`
- [ ] ...

## Watch for (likely to trip the audit)
- <pattern> — what to do instead

## Surface to user before writing
- <design-level decision the parent should confirm before coding>
```

Return only the checklist. Don't write code. Don't make recommendations beyond the rules. Skeptical stance — if a rule doesn't apply to the planned changes, omit it (no padding).
```

## When to use

- New REST route or AJAX endpoint
- New policy or capability helper
- New migration or schema-shape change
- New module under `Modules/` or pro `app/Modules/`
- New Pinia store
- New integration / webhook / OAuth handler
- New scheduler / hook emission
- Multi-file refactor crossing repo boundary
- Anything where the parent's plan touches 4+ rulesets

## When NOT to use

- Single-file fix
- Doc-only edit
- Tiny refactor inside one method
- Just-load-the-rulesets-and-code is faster
