# Screen — Event detail

> Part of the [v1 build plan](../v1_docs.md). Conventions: [README.md](README.md).

**Route** `#/events/:id` · **Nav** none · **Phase** 3–4 · **Module**
`resources/admin/modules/events/`

---

## Purpose

One event: what it is, and who is coming.

## Layout

- **Breadcrumb** — Events › {event title}.
- **Header** — cover image and title, with a **more** button on the right opening **Edit**
  and **Delete**.
- **Two tabs, and only two:** Overview · Guests.

No Insights tab, no invites, no export in v1.

## Overview tab

| Field | Where it lives |
|---|---|
| Cover image | `event_details.settings.cover_url` |
| Title | `wp_posts.post_title` |
| Description | `wp_posts.post_content` |
| Start / end | `event_details.start_at` / `end_at`, rendered in `event_details.timezone` |
| Location type | `event_details.venue_type` |
| Online meeting URL | `event_details.settings.online_meeting_url` |
| Address | `event_details.settings.address` (JSON) |

### No venue taxonomy in v1

Location is either an online URL or an address blob in `settings`, nothing more. The
`fevent_venue` taxonomy is still registered in Phase 1 — locked design, declared by the CPT
— but **v1 writes nothing to it**. Same for `fevent_organizer` and `fevent_performer`.

Reusable venue terms later means migrating these settings blobs into terms, so this is a
deliberate trade, not an oversight.

### `venue_type` uses two of its three values

The schema allows `offline | online | hybrid`. v1 uses the first two; `hybrid` stays a
valid value nothing sets.

### The online URL is user input

It is entered by a host and rendered as a link, so **filter the protocol before binding** —
`https?:` only. A raw `javascript:` in that field is live otherwise. See
[vue.md](../../../../rules/vue.md).

## Guests tab

Everyone who responded: name, email, response (going / not going), when.

Rows come from `wp_fe_guests` filtered on `event_id`. That is the whole tab — no invites,
no check-in, no export.

Depends on the `guests.status` vocabulary question (§7.1 in
[v1_docs.md](../v1_docs.md)) — the column currently documents
`approved | checked_in | cancelled`, with no going / not-going.

## Deletes are always confirmed

Every destructive action goes behind a confirmation. The scaffold ships
[Confirm.vue](../../../../../resources/admin/components/Confirm.vue) — an `el-popover` with
a `reference` slot defaulting to a danger delete button.

**Two defects to fix before it is used widely:**

1. **Hardcoded English labels** — literal `cancel` and `confirm`, not `$t()`. Every use
   inherits an untranslatable dialog.
2. **It renders `v-html="message"`.** The moment a caller passes `Delete "{{ event.title }}"?`,
   a title containing markup becomes an injection point — and titles are user input. Switch
   to `{{ }}`, or sanitize server-side before the string reaches the prop. See
   [vue.md](../../../../rules/vue.md) and
   [fix-patterns.md](../../../../rules/fix-patterns.md).

UI confirmation is not authorization: the destructive route needs its own policy method,
never the `verifyRequest()` fallback ([routes.md](../../../../rules/routes.md)).

## Deleting an event

The event is a CPT post **and** an `event_details` row **and** possibly `guests` rows.
Deletion must remove all three atomically — a `DB::transaction` at the service layer, not
three sequential calls that can half-fail.
