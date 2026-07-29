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
| Slug | `slug` | derived | Auto-filled from the title, editable. Unique across calendars |
| Description | `description` | no | Plain text |
| Visibility | `visibility` | defaulted | `public` · `unlisted` · `private`, defaulting to `public` |

Not on this screen:

- **`status`** defaults to `active`. Archiving is a management action on an existing
  calendar, not a creation choice.
- **Logo and cover** (`meta.logo_url`, `meta.cover_url`) belong to calendar settings. Asking
  for images before the calendar exists puts a media picker in front of the first thing a
  new user ever does.
- **`created_by`** is set server-side from the current user. Never accept it from the
  payload.

## Slug behaviour

The one field with real logic behind it.

- **Auto-derive from the title as the user types**, until they edit the slug themselves —
  after that, stop overwriting their input.
- **Sanitize server-side** with `sanitize_title()` regardless of what arrives. The client
  copy is a convenience, not the authority.
- **`slug` is UNIQUE** (`uq_calendars_slug`). A collision must come back as a field-level
  error on the slug input, not a 500 and not a silent suffix. Someone reusing a name should
  be told, not quietly given `my-calendar-2`.

## Validation

Server-side, at the controller entry:

```php
$this->validate($data, [
    'title'      => 'required|string',
    'slug'       => 'nullable|string|unique:fe_calendars,slug',
    'visibility' => 'required|in:public,unlisted,private',
]);
```

Rules carry the real vocabulary rather than settling for `required|string` — see
[patterns/backend.md](../../../patterns/backend.md).

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
