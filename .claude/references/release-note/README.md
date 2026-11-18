# How to Prepare a FluentEvent Release

This is the team checklist for taking a release from the compliance check to the published
blog post with Claude Code. The AI workflows are skills in `.claude/skills/`. You don't need to
read them. This page is the human checklist.

## Prerequisites

- **Claude Code** running in this repo (`fluent-event/`)
- **WP-CLI** working in the local WordPress install (Plugin Check, POT)
- **Composer and Node 18+** (the build)
- **Playwright MCP** in Claude Code (renders the feature image)
- The release's PRs merged to `develop`

## The release flow

Run these in order, each as a message to Claude Code.

### 1. Plugin Check

```
/plugin-check
```

Runs `wp plugin check fluent-event` and writes a report to `plugin-check/report-YYYY-MM-DD.md`.
Review it, delete the rows you don't want fixed, then tell Claude **"fix the reports"**. Claude
applies the fixes, runs the check again, and opens a PR. Merge it before you continue.

### 2. Draft the changelog

```
/generate-changelog 1.1.0
```

Adds a `= 1.1.0 (...) =` block to `readme.txt` from the commits since the last release, and
writes a PR map to `release-note/1.1.0/changelog-prs-1.1.0.md`. Review the block and edit the
wording now: the release note is built from the final block.

### 3. Build the release

```
/release-ready 1.1.0
```

- Bumps the version (`fluent-event.php` header and `FLUENT_EVENT_PLUGIN_VERSION`,
  `readme.txt` `Stable tag:`)
- Checks the translation map and regenerates `language/fluent-event.pot`
- Runs the tests
- Builds `builds/fluent-event-1.1.0.zip` (direct installs) and
  `builds/fluent-event-1.1.0-wporg.zip` (WordPress.org)

It does not commit. Ask Claude to commit and open the release PR when you are ready.

### 4. Release note and feature image

```
/generate-release-note 1.1.0
```

- Claude reads the 1.1.0 block, picks the marquee features, and writes the post in
  **Gutenberg block HTML**
- Paste any copy you or a teammate wrote into the same message. Claude **checks every claim
  against the code** (limits, defaults, names, entry modes) and lists what it corrected
- Claude renders the 1200×630 feature image from the HTML template

Output goes to the gitignored `release-note/1.1.0/` folder:

| File | What it is |
|------|-----------|
| `release-note-1.1.0.html` | The post. Paste it into the block editor (Code editor mode) |
| `FeatureImage-1.1.0.png` | The featured / OG image, 1200×630 |
| `_feature-image-1.1.0.html` | The image source. Ask Claude to change it and render again |
| `changelog-prs-1.1.0.md` | The PR behind each changelog line |

Publish checklist:

1. Take the screenshots the post asks for. Each `<img src="" alt="...">` has alt text that says what to capture
2. Upload the screenshots and `FeatureImage-1.1.0.png` to the blog's media library
3. Fill each empty `src=""` with the uploaded URL
4. Paste the HTML into the block editor, set the featured image, and copy the meta description and excerpt from the `<!-- SEO & MARKETING CONTENT -->` comment into Yoast
5. Delete the SEO comment before you publish if you don't want it in the post content (HTML comments don't render)

## Tips

- **Promote a section:** "give X its own H2 section with an image".
- **Title focus:** the default title names 2–3 marquee features. Say "focus the title on the
  FluentCommunity integration" to scope it.
- **Image changes:** "move the card / shorten that label". Claude edits the HTML, renders it
  again, and shows you the PNG.
- **Never commit `release-note/`, `plugin-check/` or `builds/`.** They are gitignored on purpose.

## Files in this folder

| File | Use |
|------|-----|
| `rules.md` | Tone and voice for the post |
| `release-image-template.html` | Feature image template (FluentEvent indigo and coral, loads `fe-logo.svg`) |
| `fe-logo.svg` | FluentEvent logo, copied beside the image |
| `example-release-note-fcom-2.6.0.html` | A FluentCommunity post. Copy its structure, not its words. Replace with FluentEvent's first published post |
| `example-feature-image-fcom-2.6.0.html` | A FluentCommunity feature image, as an illustration example |
