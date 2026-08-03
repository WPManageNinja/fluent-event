# Screen — Create calendar

> Part of the [v1 build plan](../v1_docs.md). Conventions: [README.md](README.md).

**Route** `#/calendars/new` · **Nav** none — reached from the Calendars grid · **Phase** 2 ·
**Module** `resources/admin/modules/calendars/`

---

## Purpose

Creates a calendar. It is the first thing a new install must do — no event can exist without
one — so this doubles as the onboarding entry point.

## Entry points

- **Create button** in the Calendars grid header.
- **Empty state** of the Calendars grid, which on a fresh install is the only route into the
  product. See [calendars.md](calendars.md).

## A screen, not a modal

Full route rather than a dialog, so the URL is shareable, the browser back button works, and
there is room to grow as calendar fields accumulate.

> **Do not copy `fluent-booking`'s `CreateNew.vue`.** Despite the name it is a three-step
> onboarding wizard that offers to install FluentCRM, FluentSMTP and FluentCart. Ours is a
> plain form. Take layout cues from [patterns/admin-layout.md](../../../patterns/admin-layout.md)
> instead.

## Fields

Every field maps to a `wp_fe_calendars` column — nothing here is speculative:

| Field | Column | Required | Notes |
|---|---|---|---|
| Title | `title` | yes | The only field a user must think about |
| Slug | `slug` | yes | Auto-filled from the title client-side, editable. Unique across calendars |
| Description | `description` | no | Plain text |
| Visibility | `visibility` | yes | `public` · `private` · `unlisted`, the form defaults to `public` |
| Status | `status` | yes | `active` · `archived` · `draft`, the form defaults to `active` |

**Every field except description is required on the wire.** The form supplies sensible
defaults for slug, visibility and status so the user still only has to think about the
title — but the client always sends all five. The server does not infer any of them, which
keeps one rule set covering create and update rather than two.

Not on this screen:

- **Logo and cover** (`meta.logo_url`, `meta.cover_url`) belong to calendar settings. Asking
  for images before the calendar exists puts a media picker in front of the first thing a
  new user ever does.
- **Theme colour** (`meta.theme_color`) — the endpoint accepts it and falls back to the
  default when absent, but it belongs on the detail screen alongside the other appearance
  settings.
- **`created_by`** is set server-side from the current user. Never accept it from the
  payload.

## Slug behaviour

The one field with real logic behind it.

- **Auto-derive from the title as the user types**, until they edit the slug themselves —
  after that, stop overwriting their input. This is a client-side convenience only; the
  slug is user-editable, so it travels on the request and the server never derives it.
- **Sanitize server-side** with `sanitize_title()` regardless of what arrives, then
  validate — the uniqueness check has to run against the canonical slug, not the raw input,
  or `"Team Offsite"` passes the check and then collides with `team-offsite` on insert.
- **`slug` is UNIQUE** (`uq_calendars_slug`). A collision must come back as a field-level
  error on the slug input, not a 500 and not a silent suffix. Someone reusing a name should
  be told, not quietly given `my-calendar-2`.

## Validation

Server-side, at the controller entry:

Rules, messages and per-field sanitizing live in `CalendarRequest`, not inline in the
controller, so create and update share one definition:

```php
const RULES = [
    'title'       => 'required|string|max:255',
    'slug'        => 'required|string|max:255|unique:fe_calendars,slug',
    'description' => 'nullable|string',
    'visibility'  => 'required|string|in:public,private,unlisted',
    'status'      => 'required|string|in:active,archived,draft',
    'theme_color' => 'nullable|regex:/^#[A-Fa-f0-9]{3}([A-Fa-f0-9]{3})?$/',
];
```

Rules carry the real vocabulary rather than settling for `required|string` — see
[patterns/backend.md](../../../patterns/backend.md).

Two things the shape encodes:

- **Update reuses this set** with the unique rule rebuilt as
  `unique:fe_calendars,slug,{id},id`, so a calendar keeping its own slug on edit does not
  collide with itself.
- **Write the `theme_color` pattern with `?`, not `{1,2}`.** The rule parser comma-splits
  parameters, so `{1,2}` is torn in half and the regex never compiles. Same reason a rule
  regex can never contain a `|`.

Client-side validation mirrors *required* only. Uniqueness is a server answer.

## On success

Redirect to the new calendar's detail screen, `#/calendars/:id` —
[calendar-detail.md](calendar-detail.md).

That lands the user where the obvious next action is: adding an event. Returning to the grid
instead would make them find the calendar they just made and click into it.

## Permissions

Requires `create_calendars`. A user without it must not reach this screen: no Create button,
no route, and the write endpoint refuses regardless — see
[permissions.md](../permissions.md).

## Rules

- **Extract fields explicitly.** Never `Calendar::create($request->all())` — mass assignment
  is banned in [always.md](../../../../rules/always.md).
- Disable the submit button while the request is in flight, or a double-click creates two
  calendars.
- Surface the failure. A silent `.catch()` here means a user who thinks they created
  something that does not exist.
- Every label and message through `$t()`.
