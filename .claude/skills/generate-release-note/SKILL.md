---
name: generate-release-note
description: Generate the FluentEvent release-note blog post (Gutenberg block HTML) and the 1200×630 feature image for a release version. Reads the changelog block from readme.txt, turns entries into user-benefit sections, fact-checks every claim (including team-written drafts) against the code, and renders the cover image with Playwright. Writes to the gitignored release-note/X.Y.Z/ folder. Use when the user asks for a release note, release blog post, or release feature image.
argument-hint: <version>
---

# /generate-release-note — Release Blog Post + Feature Image

Write the paste-ready blog post and the feature image for a FluentEvent release. The human
checklist for the team is `.claude/references/release-note/README.md`.

## Input

`$ARGUMENTS`

- **Required:** the target version (for example `1.1.0`). The `= X.Y.Z (...) =` block MUST
  already be in `readme.txt`. No block → stop and tell the user to run `/generate-changelog` first.
- **Optional:** section drafts written by the team, in this message or earlier in the
  conversation. They are candidates, not facts. Fact-check them (Step 2).
- **Optional:** image URLs. Without them, use `src=""` with descriptive alt text. Don't ask.

## Fixed decisions — do NOT ask about these

1. **Format:** Gutenberg block HTML (`wp:paragraph`, `wp:heading`, `wp:image`, `wp:list`). All
   files go in `release-note/X.Y.Z/` at the plugin root (gitignored; create it if missing). The
   post is `release-note/X.Y.Z/release-note-X.Y.Z.html`.
2. **Headings:** H2, no `Added:`/`Introduced:` prefix. Benefit-led ("Share an Event in One
   Click", not "Added: Share Event Modal With the Link, Shortcode and Block").
3. **Images:** `<img src="" alt="..."/>` with an empty `src`. The user pastes URLs after the
   upload. Every dedicated section gets at least one image.
4. **Title:** `FluentEvent X.Y.Z: [Marquee 1], [Marquee 2], and [Marquee 3]` in an HTML comment
   on line 1. Each marquee is benefit-led, 2–4 words. Ask the user only whether to scope the
   title to one area (for example the FluentCommunity integration).
5. **FluentCommunity features:** a feature that works only inside FluentCommunity says so at its
   first mention in the body ("in your FluentCommunity portal"). The heading stays clean. Check
   in the code (`Modules/FluentCommunity/`); don't guess.

## Workflow

### 1. Read the changelog and tier the entries

From the target block in `readme.txt`:

- `Introduced:` → its own H2 section (tier 1).
- `Added:` / `Improved:` with a visible UI → its own H2 or a grouped H2 (tier 2). If the user
  asks to promote a grouped item to its own H2, do it.
- Everything else → grouped sections with bold-lead paragraphs, NOT a raw bullet list.

`release-note/X.Y.Z/changelog-prs-X.Y.Z.md` (from `/generate-changelog`) maps each entry to its
PR. Use it to find the code behind an entry.

### 2. Fact-check every claim against the code, team drafts included

For every concrete claim (limits, defaults, names, times, entry modes, who can do what):

- grep the code that implements it, and read it.
- `git show <sha>` the commits behind the entry (the PR map has them).
- Check against the data model in `CLAUDE.md`: entry modes (`rsvp`, `open`, `none`), statuses
  (`public`, `unlisted`, `private`, `draft`), capacity, what a visitor vs a member sees.
- When the code contradicts the copy, correct the copy and list the correction in the summary.

### 3. Write the post

Structure (match `.claude/references/release-note/example-release-note-fcom-2.6.0.html`; it is
a FluentCommunity post, so copy its structure, not its words):

1. `<!-- Title: ... -->` comment
2. Two lead paragraphs: the theme, then "at a glance" with the marquee names in `<strong>`
3. Tier 1/2 H2 sections: image(s), 2–3 short paragraphs, an optional `wp:list` only for 3+
   outcomes
4. Grouped polish section(s): bold-lead paragraphs
5. "Bug Fixes and Stability": bold-lead grouped paragraphs (WordPress and database compatibility
   first, then themed groups: registration, emails, FluentCommunity, everyday polish)
6. "Wrapping Up": a short summary and an update-when-ready closer
7. "Full Changelog" H2 + H3 `FluentEvent X.Y.Z` + a `wp:list` of the exact `readme.txt` lines
   (the only place raw changelog lines go)
8. A trailing `<!-- SEO & MARKETING CONTENT -->` comment: alt text for every image (featured and
   per section), a Yoast meta description (160 characters at most), and an excerpt.

Tone: `.claude/references/release-note/rules.md`. Simple words, short paragraphs, benefit first,
no hype adjectives, brand casing (`FluentEvent`, `FluentCommunity`, `FluentCRM`, `RSVP`,
`Google Calendar`, `RTL`).

### 4. Feature image (1200×630)

1. Copy `.claude/references/release-note/release-image-template.html` to
   `release-note/X.Y.Z/_feature-image-X.Y.Z.html`, and copy
   `.claude/references/release-note/fe-logo.svg` beside it (the template loads it by a relative
   path).
2. Left column: badge `Release X.Y.Z`, a two-line title (line 2 in the accent colour via
   `.line2`), a one-sentence tagline. Don't touch the logo.
3. Right column: a custom illustration of the marquee feature in inline-styled divs. Mock the
   real UI (event cards, the registration popover, a countdown, a calendar grid, the share
   modal), not generic art. Overlapping cards work well for "two views" stories. See
   `.claude/references/release-note/example-feature-image-fcom-2.6.0.html`.
4. Render: Playwright blocks `file://`. Serve the folder with
   `python3 -m http.server 8731 --directory release-note/X.Y.Z` (in the background), open
   `http://localhost:8731/_feature-image-X.Y.Z.html`, and screenshot the `.banner` element as a PNG.
5. **Look at the screenshot** (Read the PNG). Fix overlaps and clipped text, and render again
   until it is clean.
6. Save it as `release-note/X.Y.Z/FeatureImage-X.Y.Z.png`. Check 1200×630 with `sips -g pixelWidth -g pixelHeight`.
   Stop the server and close the browser tab.

### 5. Print the summary

- The output paths (post HTML, image HTML, PNG)
- Each fact-check correction to team copy, with the code reference
- TODOs: image `src` URLs to fill, the featured-image upload

## Quality gate

- [ ] Every `Introduced:` entry has its own H2 with an image placeholder
- [ ] No raw changelog wording outside the Full Changelog section
- [ ] Every claim from the team drafts is verified or corrected against the code
- [ ] FluentCommunity-only features say so at first mention
- [ ] The PNG is exactly 1200×630 and you looked at it
- [ ] The SEO comment has alt text for every image in the post
