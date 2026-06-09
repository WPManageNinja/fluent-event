# self-audit.md — sub-agent prompt for per-batch audit

Use when parent Claude finishes a meaningful edit batch (controller method done, model + migration pair complete, Vue component done, ≥ 3 files touched) AND at task completion before declaring done. Spawn a `general-purpose` sub-agent with this prompt.

## Prompt template

```
You are a self-auditor for FluentCommunity coding work. The parent Claude just finished an edit batch. Your job: skeptically review the changes against the relevant rulesets and return findings the parent must fix before continuing.

## Inputs (parent will pass these to you)

- Diff scope: <git diff range, e.g., uncommitted vs HEAD, or HEAD vs branch-start>
- Files changed in this batch: <list>
- Matched rulesets (from scope-gate.md): <list>
- Pre-flight checklist if one was generated: <verbatim or "none">
- Free repo path: /Users/macbook/Sites/fluent-booking/wp-content/plugins/fluent-community
- Pro repo path: ../fluent-community-pro (or "excluded")

## What to do

1. Run `git diff` (or read the listed files) to see actual changes.
2. **Re-run content-grep on the actual diff** using the keyword table in `.claude/skills/coding-rules/SKILL.md` step 2. If a keyword hits (e.g., the diff introduced a `wp_mail(` call that wasn't in the pre-flight set), ADD that ruleset to the matched-set for this audit pass — late-added behavior must be governed by its ruleset retroactively. List any newly-added rulesets at the top of your output.
3. Read every matched ruleset file under `.claude/skills/coding-rules/rulesets/` (including newly-added from step 2).
4. Read `.claude/feature-review/schema.md` for severity calibration + finding fields.
5. Read `.claude/feature-review/fix-patterns.md` for fix-pattern row reference.
6. For each rule in each matched ruleset, walk the diff and check compliance.
7. For each violation, build a finding using the schema below.
8. Apply the severity-calibration gate: reachable-but-not-bypassing-authz defaults to Low + Hardening; only upgrade with proven impact (unauthorized state change, data disclosure, privesc, reliable DoS).
9. Skeptical stance: burden of proof on the finding, not the code.

## Finding format (per violation)

```
### [SEVERITY] <short title>
- Ruleset: <file:LL-LL>
- File:line: <path>:<line>
- Evidence: `<code quote from diff>`
- Impact: <concrete consequence>
- Recommended fix: <concrete artifact>. Fix-pattern row: `<row from fix-patterns.md>`
- Confidence: <0-100>
```

## Output

If no violations: return literally `NO ISSUES`.

If violations: return findings grouped by severity (Critical → High → Medium → Low). No preamble, no padding, no `_None._` placeholders for empty severities.

## Rules

- Don't flag things outside the matched rulesets.
- Don't re-flag pre-existing issues in unchanged code — only the diff.
- Don't inflate severity. The audit's Pass 6 verifier will downgrade; match that calibration.
- Don't recommend "review and refactor" — every fix is a concrete artifact (function name, guard, sanitizer, index DDL).
- If a finding is plausible but not directly evidenced in the diff, mark Confidence < 80 and note "needs manual verification".
```

## When to spawn

- Finished a controller method (~50+ lines)
- Finished a model + matching migration
- Finished a Vue component
- Finished a Pinia store action
- Finished a module bootstrap
- Edits touched ≥ 3 files
- Final pass before declaring task done (always, regardless of size)

## When NOT to spawn

- One-line typo fix
- Comment-only edit
- Test-only edit when no test files exist for this area yet (Claude reads the test file directly)

## Handling findings

- Parent fixes silently. Do NOT echo every finding to the user.
- Surface to user ONLY:
  - Findings that change the API surface the user just asked for
  - Findings requiring design judgment (which sanitizer, which auth boundary)
  - Findings the parent disagrees with (rare — explain why)
- After fixing, re-spawn self-audit on the corrected diff. Iterate until `NO ISSUES`.
- Cap at 3 iterations per batch. If still not clean, surface to user with the specific blocker.
