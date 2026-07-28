---
name: commit-push-pr
description: Full git workflow — stage, commit, push, and open a PR with a concise why-first description. Use when users ask to commit changes, push a branch, create a pull request, or do the full commit-push-PR flow in one step.
---

# Commit → Push → PR

Run the git workflow: stage changes, commit, push the branch, and open a PR with a quality
description. **How much of it runs is decided by the user's request** — see Scope below.

## Scope — obey the request

Read the user's wording first, then run only the steps it asks for. The workflow below is
the full menu, not a mandate.

**Nothing specified** — "commit this", "/commit-push-pr", "do the git thing" — run the whole
flow: commit → push → PR.

**A subset named** — run exactly that and stop. Do not continue into later steps.

| User says | Run | Stop after |
|---|---|---|
| "just commit" / "only commit" | Steps 1–2 | commit — no push, no PR |
| "commit and push" | Steps 1–3 | push — no PR |
| "push" (already committed) | Step 3 | push |
| "open a PR" / "raise a PR" (already pushed) | Steps 4–7 | PR |
| "commit, push and PR" / nothing said | Steps 1–7 | PR |

**Files named** — "commit the .gitignore", "only the schema docs" — stage exactly those paths
and leave every other change unstaged. With no files named, stage all relevant changes
(still skipping anything secret-looking).

**Anything else the user pins down** — branch name, base branch, commit subject, PR title,
skipping the branch step and committing on the current branch — takes precedence over the
defaults in this file. The user's instruction wins; this skill only fills the gaps.

Stopping early is a correct outcome, not an incomplete one. Report what ran and what you
deliberately skipped, and never silently run a step the user excluded.

Note: the repo's own git guard asks for confirmation before push/PR. When scope is
unspecified, still confirm before Step 3 rather than pushing unprompted.

## Constraints

- Do NOT add any `Co-Authored-By` trailer anywhere — not in the commit message, not in PR body. Never reference Claude/Anthropic as co-author.
- Commit subject and PR title MUST use **Title Case** (capitalize principal words; keep articles/prepositions/conjunctions <4 letters lowercase unless first/last word).
- If the user provides a **ticket/task/issue link or ID** before requesting the commit (e.g. Jira/Linear/GitHub/GitLab/Asana/ClickUp/Trello URL or `#123`/`PROJ-456`), capture it and include it in BOTH the commit message body AND the PR body. Never invent or guess a ticket reference; only use what the user supplied.

## Execution Workflow

### Step 1 — Determine branch target

- If on `master`, or `develop`, create a new branch first:
  - Base new branches on `develop`. If `develop` exists (local or `origin/develop`): `git checkout develop && git pull` (skip pull if offline). If it does not exist, fall back to the first available of `master`, `main`, `dev` (same order as Step 4) and branch from there. Then `git checkout -b <type>/<short-slug-from-changes>`.
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
  - Subject ≤50 chars, Title Case, no trailing period. Subject states the *what*.
  - Body (optional) states the *why* if non-obvious. Wrap at ~72 chars.
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

- PR base branch is **`develop`** — always target `origin/develop`.
- Only if `develop` does not exist on the remote, fall back to: `origin/master`, `origin/main`, `origin/dev` (in that order), and ask the user if still ambiguous.

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

**What does this PR do and why?** *(required)*
- First sentence: the underlying problem or need.
- Second sentence: the solution approach.
- Optional third: impact/scope boundary.
- If the user supplied a ticket/task link or ID, include it on its own line at the end of this section using the matching closing keyword (`Fixes #123`, `Closes PROJ-456`, `Refs: <url>`). Use full URL when given; raw IDs when given. Do not fabricate.

**Changes** *(conditional)*
- Include only if it adds meaningful reviewer context.
- 1–4 concise bullets by capability/area. No raw filenames.
- Omit when obvious or redundant.

**How to test** *(conditional)*
- Numbered steps with expected outcomes.
- Omit when no meaningful test flow is available.

**Screenshots** *(conditional)*
- Include only for visual UI changes. Omit otherwise.

**Anything the reviewer should know?** *(conditional)*
- Risks, trade-offs, migrations, rollout notes, known limits only.
- Omit when nothing material to flag.

## Concision Rules

- Keep wording direct, plain, and compact.
- Remove filler and repeated statements.
- Prefer short sentences and concrete nouns/verbs.
- Keep overall output as short as possible without losing key context.

### Step 7 — Open PR

- `gh pr create --base <base-branch> --title "<title>" --body "<filled-template>" --assignee @me`
- Always self-assign the PR (`--assignee @me`). If creation succeeded without it, run `gh pr edit <url> --add-assignee @me`.
- Title: same subject as the commit message in Title Case, including the prefix (`Fixed:`, `Added:`, `Improved:`, etc.) when one fits. For multi-commit PRs, restate cleanly using the dominant prefix.
- Return the PR URL.

## Quality Gate

Verify before creating the PR:
- Opening section clearly answers *why* this PR is needed.
- Optional sections present only when meaningful.
- All statements are evidence-backed.
- No fluff, no bloat, no redundant details.
- Commit subject AND PR title in **Title Case**, with a `readme.txt`-style prefix where one fits.
- If user supplied a ticket reference, it appears in BOTH the commit body trailer AND the PR body. Never invented.
- No `Co-Authored-By` line anywhere in commit or PR body. No Claude/AI co-author attribution.
- PR is self-assigned (`--assignee @me`).

## References

- `references/pull_request_template.default.md`
