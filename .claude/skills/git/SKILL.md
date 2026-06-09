---
name: git
description: Full git workflow — stage, commit, push, and open a PR with a concise why-first description. Use when users ask to commit changes, push a branch, create a pull request, or do the full git flow in one step.
---

# Commit → Push → PR

Run the full git workflow: stage changes, commit, push the branch, and open a PR with a quality description. Read the custom argument if the user wants all three or any specific events. Default assume all.

## Constraints

- Do NOT add any `Co-Authored-By` trailer anywhere — not in the commit message, not in PR body. Never reference Claude/Anthropic as co-author.
- Commit subject and PR title MUST use **Title Case** (capitalize principal words; keep articles/prepositions/conjunctions <4 letters lowercase unless first/last word).
- If the user provides a **ticket/task/issue link or ID** before requesting the commit (e.g. Jira/Linear/GitHub/GitLab/Asana/ClickUp/Trello URL or `#123`/`PROJ-456`), capture it and include it in BOTH the commit message body AND the PR body. Never invent or guess a ticket reference; only use what the user supplied.

## Execution Workflow

### Step 1 — Determine branch target

- If on `master`, or `develop`, create a new branch first:
  - `git checkout -b <type>/<short-slug-from-changes>`
  - **Branch prefix** is derived from the change type (align it with the commit prefix chosen in Step 2):
    - `fix/` — bug fix or regression (commit `Fixed:` / `Resolved:`).
    - `hotfix/` — urgent patch on top of a release (commit `Hotfix:`).
    - `feat/` — new feature, sub-feature, option, field, hook, integration (commit `Introduced:` / `Added:`).
    - `improve/` — enhancement or behavior/copy/dependency adjustment (commit `Improved:` / `Updated:`).
    - `security/` — security patch or hardening (commit `Security:`).
    - `chore/` — removals, refactors, tooling, config (commit `Removed:` or anything without a clear prefix).
  - Slug: lowercase, kebab-case, 2–5 words describing the change. Example: `fix/submit-button-not-working`.
- Otherwise, stay on the current branch.

### Step 2 — Stage and commit

- Run `git status` and `git diff HEAD` to review changes.
- Stage all relevant modified/new files (`git add`). Skip files that look like secrets (`.env`, credentials, keys).
- Draft a commit message:
  - **Preferred format** (matches `readme.txt` changelog style): `<Prefix>: <Subject in Title Case>`.
    - Allowed prefixes (pick the closest fit; not mandatory if none fit):
      - `Introduced:` — brand-new major feature/module.
      - `Added:` — new sub-feature, option, field, hook, integration.
      - `Improved:` — enhancement to existing UI/UX/perf without behavior change.
      - `Updated:` — adjustment to existing behavior, dependency, or copy.
      - `Fixed:` — bug fix.
      - `Resolved:` — fix for a reported issue / conflict / regression.
      - `Security:` — security patch, hardening, vulnerability fix.
      - `Hotfix:` — urgent patch on top of a release.
      - `Removed:` — removal of feature, file, or dependency.
  - Fallback (when no prefix above fits): Conventional Commits — `type(scope): Subject In Title Case`.
  - Subject ≤50 chars, Title Case, no trailing period. Subject states the _what_.
  - Body (optional) states the _why_ if non-obvious. Wrap at ~72 chars.
  - **Ticket reference**: if the user supplied a ticket/task link or ID earlier in the conversation, append it to the commit body as a trailer after a blank line. Use the closing keyword that fits the work:
    - Bug fix → `Fixes: <url-or-id>`
    - Feature/task → `Refs: <url-or-id>` (or `Closes:` if the user said it completes the ticket)
    - Multiple tickets → one trailer line per reference.
  - No `Co-Authored-By` line. No Claude/AI attribution.
- Run `git commit`.

#### Commit subject examples

- `Fixed: Coupon Issue with Multi-Duration Events`
- `Added: File Upload Option in Admin Booking`
- `Security: Patch Data Import/Export Vulnerability`
- `Improved: Dark Mode and Overall Responsiveness`
- `Introduced: Recurring Event Support`

### Step 3 — Push

- `git push -u origin <branch>` (set upstream if first push).

### Step 4 — Resolve PR base branch

- Prefer upstream merge target if it maps to `development`, `master`, or `main`.
- Otherwise evaluate candidates in order: `origin/development`, `origin/master`, `origin/main`, `origin/dev`, then local equivalents.
- Multiple candidates: use `git merge-base HEAD <candidate>` — pick the most recent merge-base.
- Ambiguous: ask the user which base branch to target.

### Step 5 — Gather change evidence for PR body

- `git diff --name-status <base>...HEAD`
- `git diff --stat <base>...HEAD`
- `git log --oneline <base>..HEAD`
- Inspect changed files as needed to extract intent and testing details.

### Step 6 — Fill PR template

Resolve template path in order:

1. `.github/pull_request_template.md`
2. `.github/PULL_REQUEST_TEMPLATE.md`
3. `references/pull_request_template.default.md` (fallback)

#### Template section rules

**What does this PR do and why?** _(required)_

- First sentence: the underlying problem or need.
- Second sentence: the solution approach.
- Optional third: impact/scope boundary.
- If the user supplied a ticket/task link or ID, include it on its own line at the end of this section using the matching closing keyword (`Fixes #123`, `Closes PROJ-456`, `Refs: <url>`). Use full URL when given; raw IDs when given. Do not fabricate.

**Changes** _(conditional)_

- Include only if it adds meaningful reviewer context.
- 1–4 concise bullets by capability/area. No raw filenames.
- Omit when obvious or redundant.

**How to test** _(conditional)_

- Numbered steps with expected outcomes.
- Omit when no meaningful test flow is available.

**Screenshots** _(conditional)_

- Include only for visual UI changes. Omit otherwise.

**Anything the reviewer should know?** _(conditional)_

- Risks, trade-offs, migrations, rollout notes, known limits only.
- Omit when nothing material to flag.

## Concision Rules

- Keep wording direct, plain, and compact.
- Remove filler and repeated statements.
- Prefer short sentences and concrete nouns/verbs.
- Keep overall output as short as possible without losing key context.

### Step 7 — Open PR

- `gh pr create --base <base-branch> --title "<title>" --body "<filled-template>"`
- Title: same subject as the commit message in Title Case, including the prefix (`Fixed:`, `Added:`, `Improved:`, etc.) when one fits. For multi-commit PRs, restate cleanly using the dominant prefix.
- Return the PR URL.

## Quality Gate

Verify before creating the PR:

- Opening section clearly answers _why_ this PR is needed.
- Optional sections present only when meaningful.
- All statements are evidence-backed.
- No fluff, no bloat, no redundant details.
- Commit subject AND PR title in **Title Case**, with a `readme.txt`-style prefix where one fits.
- If user supplied a ticket reference, it appears in BOTH the commit body trailer AND the PR body. Never invented.
- No `Co-Authored-By` line anywhere in commit or PR body. No Claude/AI co-author attribution.

## References

- `references/pull_request_template.default.md`
