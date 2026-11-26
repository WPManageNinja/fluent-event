# FluentEvent Changelog Conventions

Reference for the `/generate-changelog` skill. Also for anyone who edits the `readme.txt`
changelog by hand.

FluentEvent has no pro plugin. One `readme.txt` holds the whole changelog.

## Version heading format

```
= X.Y.Z (Date: Mon DD, YYYY) =
```

- 3-letter month, no period (`Jan`, `Feb`, `Mar`, `Apr`, `May`, `Jun`, `Jul`, `Aug`, `Sep`, `Oct`, `Nov`, `Dec`).
- Day with no leading zero (`Nov 5, 2026`, not `Nov 05, 2026`).
- Year as 4 digits.
- A hotfix release uses the same format and usually holds only `Hotfix:` entries.

Generate the date with:

```bash
date "+%b %-d, %Y"   # macOS / Linux compatible
```

## Bullet format

Every entry starts with `- ` (hyphen and space):

```
- Added: Live Countdown to an Event's Start
- Improved: Guest Count in the Event Page Overview Tab
- Fixed: Tables Not Created on Older MySQL and MariaDB
```

## Prefix vocabulary

| Prefix | Meaning | Use when |
|--------|---------|----------|
| `Introduced:` | A new module or concept, first release | A marquee feature. Use it for 0–2 entries per release. |
| `Added:` | A new option, setting, hook or smaller capability | A new API, setting, hook, block, shortcode, or a sub-feature of an existing area. |
| `Improved:` | An existing feature works better | Performance, UX, polish. No new capability. |
| `Security:` | Security hardening | Merge every security commit in the release into ONE entry. Never more than one `Security:` line per release. |
| `Fixed:` | A bug fix | A defect that affects guests, admins, or integrations. |
| `Hotfix:` | An emergency patch | Only in a hotfix release. Never in a regular release. |

Commit prefixes that are not changelog prefixes map like this:

- `Updated:` → `Improved:` when the change helps the user, `Fixed:` when it corrects behaviour. Exclude it when nobody sees the change (docs, copy in dev files).
- `Resolved:` → `Fixed:`.
- `Removed:` → ask the user. A removed feature that users had needs an entry. A removed internal file does not.

Do **not** use `New:`, `Refactored:`, `Redesigned:`, `Enhanced:`, `Improvement:` or `Updated:` in the changelog.

## Entry order within a release

Strictly:

1. `Introduced:`
2. `Added:`
3. `Improved:`
4. `Security:` (when present)
5. `Fixed:`

## Casing

Title Case for all entry text after the prefix.

- `Added: Share Event Modal With the Link, Shortcode and Block` ✓
- `Added: Share event modal with the link, shortcode and block` ✗

Brand and term exceptions (keep the canonical casing): `FluentEvent`, `FluentCommunity`,
`FluentCRM`, `FluentAuth`, `FluentForms`, `FluentCart`, `Gutenberg`, `WordPress`, `RSVP`, `RTL`,
`iOS`, `iCal`, `.ics`, `Google Calendar`, `Apple Calendar`, `Outlook`, `MySQL`, `MariaDB`,
`MyISAM`, `PHP`, `WP CLI`, `CSS`, `JavaScript`, `Zoom`, `Google Meet`.

## Length

- Target ~60 characters.
- Up to ~80 when clarity needs it.

## Phrasing voice

- Descriptive noun phrases, not instructions.
- Yes: `Added: Guest Count on Admin Event Cards`
- No: `Add guest count to admin event cards`
- Name what the user sees, not the code. `Fixed: Events Page Error on a Bad Calendar Link`, not
  `Fixed: Type Check in FrontEndHandler`.

## Mechanism over enumeration

When an entry lists more than 2 things, look for one mechanism or one benefit that covers them.
The mechanism version is shorter and says what changed, not only where.

- Weaker: `Fixed: Dates in the Portal Calendar, Date Picker and Event Cards Not Translated`
- Stronger: `Fixed: Portal Dates Not Translated`

Keep the list when it is small (2 or fewer) or when the list is the point.

## Historical entries

Never fix old version blocks. `= 1.0.0 - Nov 13, 2026 =` uses an older heading. Leave it. A new
block must not edit another block.

An **unreleased** block (its version is not yet on WordPress.org or in a shipped zip) is not
historical. Bring it to this spec when you touch it, and tell the user what you changed.

## Punctuation

- No trailing periods.
- No ticket refs, PR numbers, GitHub URLs, or commit SHAs.
- Join tightly related fixes with `and`: `Fixed: Time Picker Rows and Cover Image in Community Cards`.
- No parentheses, except for a credit inside a `Security:` entry.

## Inclusion rules

**Include:**
- New features, settings, options, hooks, filters, blocks, shortcodes, REST routes
- UX/UI changes that guests or admins notice
- Security hardening (all security commits merged into exactly one entry)
- Bug fixes that affect guests, admins, or integrations (FluentCommunity, Google Calendar, email)
- New public API for developers

**Exclude:**
- Internal refactors, file moves, SCSS reorganization with no visual change
- Dev tooling (`.claude/**`, `.agent/**`, `build.sh`, `dev/`, lint config, tests) unless users see it
- Trivial subjects (`Update X.vue`, `tweaks`)
- Tests-only, language-file-only, `.gitignore`-only commits
- Docs-only commits (`docs/`, `.claude/docs/`, `CLAUDE.md`)
- Merge commits (`--no-merges` filters them)
- Version-bump and changelog-only commits
- Fixes to a feature that is new in the same release. The user never saw the bug; the `Added:`
  line covers it.

**Ask first** when:
- A query or performance change may change behaviour
- A library upgrade has an unclear behaviour change
- A revert of a change that already shipped
- A migration with no clear user-facing effect

## Examples

### Regular release

```
= 1.1.0 (Date: Dec 2, 2026) =
- Added: Live Countdown to an Event's Start
- Added: Share Event Modal With the Link, Shortcode and Block
- Improved: Guest Count in the Event Page Overview Tab
- Security: Public Registration and Email Settings Hardening
- Fixed: Tables Not Created on Older MySQL and MariaDB
- Fixed: Events Page Error on a Bad Calendar Link
```

### Hotfix release

```
= 1.1.1 (Date: Dec 4, 2026) =
- Hotfix: Registration Form Not Opening on Some Themes
```

### Security entry

**One `Security:` entry per release, always.** Merge every security commit into one umbrella
entry. Write the wording fresh each release from what the commits did, at the level that covers
the whole batch:

```
- Security: Restricted Guest Data on Public Registration Forms        ← one targeted fix
- Security: Public Registration and Email Settings Hardening          ← several fixes
```

Never split it per area:

```
- Security: Public Registration Forms No Longer Show Who Registered
- Security: Stricter Sanitizing of Email Notification Settings
```

A researcher or CVE credit goes inside the single entry: `(props to patchstack. CVE-XXXX-XXXXX)`.
If one release fixes several credited CVEs, ask the user how to format it.

## Not adopted (flag to the user, don't apply)

- A `Breaking:` or `Deprecated:` prefix
- Grouping subheaders (`### New Features`)
- A semver line under each heading
