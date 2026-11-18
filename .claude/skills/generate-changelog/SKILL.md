---
name: generate-changelog
description: Draft the readme.txt changelog block for a new FluentEvent release. Collects non-merge commits since the previous release, classifies each with the changelog prefix vocabulary (Introduced/Added/Improved/Security/Fixed), writes Title Case noun phrases, inserts the block above the previous version in readme.txt, and writes a gitignored PR map. Does NOT bump Stable tag or the plugin version; that is /release-ready. Use when the user asks to draft, generate or write the changelog for a version.
argument-hint: <version> [Mon D, YYYY]
---

# /generate-changelog — Draft the Release Changelog Block

Write one `= X.Y.Z (Date: Mon D, YYYY) =` block in `readme.txt` that follows the existing
changelog conventions exactly.

Style, prefix vocabulary, casing, brand-name exceptions, inclusion rules and examples are in
`.claude/references/changelog-conventions.md`. Read that file before you classify commits.

FluentEvent has no pro plugin. Only this repo feeds the changelog.

## Input

`$ARGUMENTS`

The first token is the target version (for example `1.1.0`). An optional second part is the
release date (`Mon D, YYYY`).

- No version → ask the user for one.
- No date → use today: `date "+%b %-d, %Y"`.
- The version already has a block in `readme.txt`:
  - The version is released (in a git tag, or the user says it shipped) → ask before you replace it.
  - The version is not released → update the block in place: keep the user's existing wording
    unless it breaks the conventions, add the new entries, and report every line you changed.
    The commit range still starts at the previous shipped release (Step 1), never at the draft.

## Output contract

- Insert the new block right after the `== Changelog ==` line, above the previous version block.
- Do NOT change any other version block (see "Historical entries" in the conventions).
- Do NOT change `Stable tag:` or `FLUENT_EVENT_PLUGIN_VERSION`. `/release-ready` does that.
- Write the PR map `release-note/X.Y.Z/changelog-prs-X.Y.Z.md` (Step 6).
- Print the block back to the user.

## Workflow

### Step 1 — Find the previous release and its commit

Read `readme.txt`. List the headings that match:

```
^= ([0-9]+\.[0-9]+\.[0-9]+) \(Date: ([A-Z][a-z]{2}) ([0-9]{1,2}), ([0-9]{4})\) =$
```

The previous release is the **newest heading whose version is lower than the target**
(`php -r 'var_dump(version_compare("1.0.5", "1.1.0", "<"));'`). Never the target itself: on a
re-run, the target's own draft block is the newest heading, and taking it gives an empty or
partial range.

The previous release must have **shipped** (a git tag, a release-branch merge, or the user says
so). A heading that is only a draft is not a boundary: skip it and take the next lower heading,
so the range covers every commit since the last shipped release. When it is unclear whether a
heading shipped, ask the user.

On a re-run for a draft block, always use this full range. Steps 2–6 rebuild the block and the
PR map from it, so no retained entry loses its source.

Then find the **release commit** for that version. A commit range is more accurate than
`--since=$DATE`: a date grabs same-day commits from before the release and misses PRs that
branched before it.

```bash
PREV_VERSION="<parsed, e.g. 1.0.5>"
PREV_DATE="<parsed, ISO form, e.g. 2026-11-17>"

# 1. A git tag for the version
PREV_REF=$(git rev-parse --verify "v$PREV_VERSION" 2>/dev/null \
        || git rev-parse --verify "$PREV_VERSION" 2>/dev/null || true)

# 2. The merge of the release branch (release-X.Y.Z)
[ -z "$PREV_REF" ] && PREV_REF=$(git log --merges --grep="release-$PREV_VERSION" --format='%H' -1)

# 3. The commit that set the version in fluent-event.php
[ -z "$PREV_REF" ] && PREV_REF=$(git log -S"'FLUENT_EVENT_PLUGIN_VERSION', '$PREV_VERSION'" --format='%H' -1 -- fluent-event.php)

# 4. Last resort: the date, and tell the user the boundary is approximate
[ -z "$PREV_REF" ] && echo "WARN: no release ref for $PREV_VERSION, using --since=$PREV_DATE (approximate)"
```

Show the user the ref you chose and how you found it. A wrong lower bound is the most common
error in this skill.

If there is no previous heading (first release), ask the user for the lower bound.

### Step 2 — Collect commits

```bash
if [ -n "$PREV_REF" ]; then
  git log "$PREV_REF..HEAD" --no-merges --format='%h%x09%s%x09%b%x1e' > "$TMPDIR/fe_commits.txt"
else
  git log --since="$PREV_DATE" --no-merges --format='%h%x09%s%x09%b%x1e' > "$TMPDIR/fe_commits.txt"
fi
```

Records end with `%x1e`. Each record has 3 tab fields: sha, subject, body.

For a vague subject (`tweaks`, `update`), read the body, or run `git show --stat --no-patch <sha>`
to see the files.

### Step 3 — Classify each commit

Apply `.claude/references/changelog-conventions.md`. For each commit decide:

1. **Include?** Use the inclusion rules.
2. **Prefix?** `Introduced` / `Added` / `Improved` / `Security` / `Fixed` (or `Hotfix` for a
   hotfix release). A commit subject that already starts with a prefix is a strong hint, since
   this repo writes commits in changelog style. Still check the body and files. Map `Updated:`,
   `Resolved:` and `Removed:` as the conventions say.
3. **Wording?** Title Case noun phrase, ~60 characters, ~80 at most. No trailing period, no
   SHA, no PR number. Name what the user sees.
4. **Same change twice?** A fix to a feature that is new in this release folds into its
   `Added:` line. Two commits for one change make one entry.

### Step 4 — Order

`Introduced:` → `Added:` → `Improved:` → `Security:` → `Fixed:`

All `Security:` commits become ONE `Security:` line.

### Step 5 — Insert into readme.txt

On a re-run for a draft block, edit that block in place instead of inserting a second one.

For a new block, use the `Edit` tool. Anchor on the previous version heading:

```
old_string:
== Changelog ==

= 1.0.5 (Date: Nov 17, 2026) =

new_string:
== Changelog ==

= 1.1.0 (Date: Dec 2, 2026) =
- Added: …
- Fixed: …

= 1.0.5 (Date: Nov 17, 2026) =
```

### Step 6 — Write the PR map

Each entry collapses one or more commits. Record where each came from, so the release note, a
reviewer or a bug report can find the source.

Write `release-note/X.Y.Z/changelog-prs-X.Y.Z.md` (gitignored; create the folder if it is
missing; `/generate-release-note` writes into the same folder). Never commit it.

Build the commit → PR map from merge commits, not from one `gh` call per commit:

```bash
build_map() {
  prev_ref="$1"
  git log "$prev_ref..HEAD" --merges --format='%H|%s' | while IFS='|' read -r m subj; do
    pr=$(echo "$subj" | sed -n 's/^Merge pull request #\([0-9]*\).*/\1/p')
    [ -z "$pr" ] && continue
    git log "${m}^1..${m}^2" --no-merges --format='%h' 2>/dev/null \
      | while read -r c; do echo "$c|$pr"; done
  done
}
build_map "$PREV_REF" > "$TMPDIR/fe_prmap.txt"
```

A commit can map to more than one PR (a sub-PR merged into a release branch, then the release
branch merged into `develop`). Pick the PR a reader should open first: the direct PR for a
small change, the umbrella branch for a multi-part feature.

Rules for the file:

- **One PR per entry.** Never a list of links in the table cell. Put the others in Notes.
- Numbered rows in the same order as the block, so row N is line N of the block.
- The `Security:` entry has no single PR. Show one representative PR with `— see note`, and
  list the full set in Notes with its count.

```markdown
# X.Y.Z Changelog — Source PRs

One PR per changelog entry: the main one, where an entry covers several.
Repo: WPManageNinja/fluent-event

| # | Changelog entry | Main PR |
|---|---|---|
| 1 | Added: … | [#439](https://github.com/WPManageNinja/fluent-event/pull/439) |

## Notes

**Entry N (Security)** covers M PRs: …
```

**Keep it in sync.** When the block changes later (reworded, merged, dropped, moved), rebuild
the table from the block in `readme.txt`. Don't patch rows by hand. Renumber the Notes with it.

### Step 7 — Print the summary

- The block, verbatim.
- The entry count per prefix.
- The PR map path.
- **Skipped commits**, each with a one-line reason, so the user can override.
- **Needs clarification**: commits where include or prefix was unclear. For each, show the PR
  number, title and URL from the Step 6 map.
- Next step: `/release-ready X.Y.Z` bumps the version and builds the zips.

## Quality gate

- [ ] The heading matches the Step 1 regex. The day has no leading zero.
- [ ] The lower bound is the newest shipped release below the target, never the target's own draft.
- [ ] Every line in the block starts with `- `.
- [ ] Entries are in prefix order. Exactly zero or one `Security:` line.
- [ ] No `Updated:`, `Resolved:`, `Removed:` or `New:` prefix in the block.
- [ ] No commit that fails the inclusion rules slipped in. No entry over ~85 characters.
- [ ] No fix to a feature that is new in this release has its own `Fixed:` line.
- [ ] `readme.txt` is intact: no broken heading, no duplicate version block, previous block untouched.
- [ ] The PR map has one row per entry, in the same order, each with exactly one PR link.
- [ ] Every PR number came from a merge commit in the range, never guessed from a subject.
- [ ] The summary lists skipped commits with reasons.
