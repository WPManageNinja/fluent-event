# Data model + implementation — event CPT hybrid + WP taxonomies

> How the schema is put together and how we build against it. Locked decisions:
> **event = CPT hybrid** (wp_posts + wp_postmeta + `wp_fe_event_details`); **category,
> venue, organizer, performer = WP taxonomies** (performer thin: name+photo, no profile).
> Diagram: [schema.svg](schema.svg). DDL: [schema.sql](schema.sql). Generator:
> [gen_schema.py](gen_schema.py) — edit the spec there, never hand-edit the 3 outputs.

## 1. Where each thing lives

| Concern | Store | Why |
|---|---|---|
| Event identity — title, description, slug, status, author | **`wp_posts`** (`post_type=fevent_event`) | free permalink `/events/x/`, block editor, admin, REST, search, archive |
| Event add-on / open-ended fields | **`wp_postmeta`** | extensible by add-ons, never range-queried. *(Core event config is the `settings` JSON **column** on `wp_fe_event_details`, not postmeta.)* |
| Event machine data — dates, timezone, venue_type, calendar_id, parent_event_id, status | **`wp_fe_event_details`** (1:1, `post_id` FK) | typed, indexed, range-queryable; what ticketing FKs to |
| Event config JSON — registration_type, join_policy, waitlist, cover_url, online_meeting_url, recurrence | **`wp_fe_event_details.settings`** (JSON column) | travels with the typed row; never range-queried, so it doesn't earn columns |
| Category / type, venue, organizer, performer | **WP taxonomies** (`wp_terms` + `wp_term_taxonomy` + `wp_termmeta`) | reusable across events, filter + free `/organizer/x/` archive + auto count |
| Ticketing — tiers, orders, attendees | **custom tables** (`ticket_types`→`guests`; an order = guest rows sharing `group_id`, `is_buyer=1` row carries payment) | high-volume, transactional, typed, `SUM`/`sold<slot` queries |
| Performer detail | `fevent_performer` term + term meta (photo, designation, social) | **thin** — a name+photo you tag events with, no bio/profile page. Migrate to a CPT only if rich profiles are ever needed. |

**Key identity rule:** `wp_fe_event_details.id` is the internal PK all ticketing FKs use
(`event_id`). `wp_fe_event_details.post_id` is the 1:1 link to the `wp_posts` row and the
**taxonomy anchor** (`wp_term_relationships.object_id` = that post id). One event = one
`wp_posts` row + one `wp_fe_event_details` row.

## 2. Registration (bootstrap)

```php
// The event as a CPT — gives us the page, editor, admin, REST, archive for free.
register_post_type('fevent_event', [
    'public'       => true,
    'has_archive'  => true,                 // /events/
    'rewrite'      => ['slug' => 'events'],
    'supports'     => ['title','editor','thumbnail','author','custom-fields'],
    'show_in_rest' => true,
]);

// Classification — reusable, filterable, browsable, with term meta.
foreach ([
    'fevent_event_type' => ['label' => 'Event Types', 'hierarchical' => true],
    'fevent_venue'      => ['label' => 'Venues',       'hierarchical' => true],
    'fevent_organizer'  => ['label' => 'Organizers',   'hierarchical' => true],
    'fevent_performer'  => ['label' => 'Performers',    'hierarchical' => false], // flat, like tags
] as $tax => $args) {
    register_taxonomy($tax, 'fevent_event', [
        'public'       => true,
        'show_in_rest' => true,
    ] + $args);
}
```

## 3. Data flow — admin creates an event

| # | Writes | Notes |
|---|---|---|
| 1 | `wp_posts` — 1 `fevent_event` row | title / editor body / slug / status=publish / author |
| 2 | `wp_postmeta` — add-on / open-ended blobs only | core event config is `event_details.settings` (step 4), not postmeta |
| 3 | `wp_term_relationships` — event ↔ event_type / venue / organizer terms | `wp_set_object_terms($post_id, $termIds, $tax)` (assign, M:N) |
| 4 | `wp_fe_event_details` — 1 row (`post_id` FK) | typed: start_at, end_at, timezone, venue_type, calendar_id, parent_event_id, status (all NOT NULL except parent_event_id) |
| 5 | `wp_fe_ticket_types` — N rows (`event_id` = events.id) | tiers |

**Service shape (WPFluent ORM + a transaction at the service layer):**
```php
DB::transaction(function () use ($data) {
    $postId = wp_insert_post([
        'post_type'   => 'fevent_event',
        'post_title'  => sanitize_text_field($data['title']),
        'post_content'=> wp_kses_post($data['description']),
        'post_status' => 'publish',
    ], true);
    // taxonomy assignment (reusable classifiers)
    wp_set_object_terms($postId, (array) $data['organizer_ids'], 'fevent_organizer');
    wp_set_object_terms($postId, (array) $data['venue_ids'],     'fevent_venue');
    wp_set_object_terms($postId, (array) $data['type_ids'],      'fevent_event_type');
    // typed machine data + the config JSON, one row
    Event::create([
        'post_id'     => $postId,                       // NOT NULL, unique
        'calendar_id' => absint($data['calendar_id']),  // NOT NULL
        'status'      => 'public',                      // public|private|draft|archived
        'start_at'    => $data['start_at'],             // UTC
        'end_at'      => $data['end_at'],               // UTC
        'timezone'    => $data['timezone'] ?: null,     // nullable
        'venue_type'  => $data['venue_type'] ?: null,   // nullable, defaults 'offline'
        'settings'    => [
            'registration_type' => $data['registration_type'],   // open|rsvp|ticketed
            'join_policy'       => $data['join_policy'],
            'waitlist_enabled'  => (bool) $data['waitlist_enabled'],
            'cover_url'         => esc_url_raw($data['cover_url']),
        ],
    ]);
});
```

## 4. Data flow — create an organizer / venue / category (a term)

An organizer is **not a post** — it's a **term** + term meta. Reusable across events.

| # | Writes |
|---|---|
| 1 | `wp_terms` — the name/slug |
| 2 | `wp_term_taxonomy` — 1 row, `taxonomy='fevent_organizer'`, `count` auto-maintained |
| 3 | `wp_termmeta` — the extra fields |

```php
$term = wp_insert_term($name, 'fevent_organizer');            // wp_terms + wp_term_taxonomy
$tid  = $term['term_id'];
update_term_meta($tid, 'fevent_email',   sanitize_email($email));
update_term_meta($tid, 'fevent_phone',   sanitize_text_field($phone));
update_term_meta($tid, 'fevent_website', esc_url_raw($site));
update_term_meta($tid, 'fevent_logo_id', absint($attachmentId));   // WP media library
```
Term meta keys per taxonomy:
- `fevent_event_type` → `fevent_color`, `fevent_icon_id`
- `fevent_venue` → `fevent_address`, `fevent_city`, `fevent_map_url`, `fevent_lat`, `fevent_lng` *(view-only; no geo query)*
- `fevent_organizer` → `fevent_email`, `fevent_phone`, `fevent_website`, `fevent_social`, `fevent_logo_id`
- `fevent_performer` → `fevent_photo_id`, `fevent_designation`, `fevent_social` *(thin — no bio/profile)*

## 5. Reads

```php
// events by organizer (reusable classifier) — the payoff of taxonomy
new WP_Query(['post_type'=>'fevent_event',
  'tax_query'=>[['taxonomy'=>'fevent_organizer','terms'=>$organizerId]]]);

// a date-range calendar view — hits the typed custom table, not meta
Event::whereBetween('start_at', [$from, $to])->where('status','active')->get();
```
- **Classification / browse / filter / archive** → taxonomy (`WP_Query` `tax_query`, `get_term_link`).
- **Dates, slots, ticket sums, check-in** → `wp_fe_event_details` + ticketing tables (real SQL, indexed).

## 6. What changed from the previous schema

- **Dropped** `wp_fe_people` (organizer + performer → taxonomies).
- **Event** reshaped into a CPT extension: `title` / `slug` / `description` → `wp_posts`;
  `visibility` → `post_status`; `created_by` → `post_author`; `cover_image_id` →
  `event_details.settings.cover_url` (a plain URL, **not** `_thumbnail_id` — see
  [FR-7 media](../functional-requirements/media-archive/media-archive.md) for the trade-off);
  `address` / `lat` / `lon` → `fevent_venue` term meta. **Added** `post_id` (1:1 FK, unique,
  taxonomy anchor).
- **Added** the 3 taxonomies + `wp_termmeta` usage (WP-native, not our tables).
- **Dropped** `wp_fe_sponsors` — not needed now. If sponsors return, they'll be a
  taxonomy (`fevent_sponsor`) like organizer, not a table.

## 7. Guardrails that apply

- Term meta values via `maybe_unserialize` are external → treat per
  [fix-patterns.md](../../rules/fix-patterns.md) (safe unserialize). Sanitize each term-meta
  field on write (`sanitize_email`/`esc_url_raw`/`absint`).
- Event body (`post_content`) rendered through `wp_kses_post`; term/name output escaped
  at the sink ([always.md](../../rules/always.md)).
- The event↔post is 1:1 — deleting the `fevent_event` post cascades the
  `wp_fe_event_details` row (`ON DELETE CASCADE`), which cascades ticket_types / guests.
  A guest's `ticket_type_id` is `RESTRICT` (can't orphan a sold ticket).
- **Order = a group of `guests` rows** sharing `group_id` (no separate registrations
  table — most orders are 1 ticket, so a split order row would double writes for the common
  case). Exactly one row per group has `is_buyer=1` and carries the order/payment fields
  (`payment_status`, `payment_ref`, `price`, `paid_at`, `source`) — **NULL on companions**.
  So `SUM(price)` = revenue with no `WHERE` guard, a payment webhook/refund updates one row
  (`WHERE group_id=? AND is_buyer=1`), ticket count = `COUNT(*)` of the group. Generate
  `group_id` at checkout (`random_bytes`/`wp_generate_password`). Optional nullable
  `user_id` links a row to a WP account when the attendee is logged in (core ref, FK in app).
- `guests.calendar_id` is a **denormalized** copy of the event's calendar, written at
  registration time so "who registered under calendar X" is a direct index scan
  (`(calendar_id, status)`) with no event join. Just copy `event_details.calendar_id` on write —
  it's NOT NULL on every row (occurrences included), so there's no inheritance to resolve.
  It's `RESTRICT` → a calendar with guests can't be deleted (the event FK already blocks it;
  belt-and-suspenders).
- **No inheritance anywhere.** `event_details` rows are self-contained: `post_id` is NOT NULL
  and UNIQUE, so a recurrence occurrence has its **own** post and its own complete typed row.
  Never write a `COALESCE(occurrence.col, master.col)` read path — that model is gone, along
  with `is_overridden`. The recurrence generator copies values at creation and never rewrites
  an existing occurrence.
