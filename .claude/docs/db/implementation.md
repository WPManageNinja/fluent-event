# Data model + implementation — event CPT hybrid + WP taxonomies

> How the schema is put together and how we build against it. Locked decisions:
> **event = CPT hybrid** (wp_posts + wp_postmeta + `wp_fevents_events`); **category,
> venue, organizer, performer = WP taxonomies** (performer thin: name+photo, no profile).
> Diagram: [schema.svg](schema.svg). DDL: [schema.sql](schema.sql). Generator:
> [gen_schema.py](gen_schema.py) — edit the spec there, never hand-edit the 3 outputs.

## 1. Where each thing lives

| Concern | Store | Why |
|---|---|---|
| Event identity — title, description, slug, status, author, cover | **`wp_posts`** (`post_type=fevents_event`) | free permalink `/events/x/`, block editor, admin, REST, search, archive |
| Event flexible / config — `_fevents_form_fields`, `_fevents_settings`, addon fields | **`wp_postmeta`** | open-ended, extensible by add-ons, never range-queried |
| Event machine data — dates, timezone, venue_type, join_policy, waitlist, calendar_id, currency, status | **`wp_fevents_events`** (1:1, `post_id` FK) | typed, indexed, range-queryable; what ticketing FKs to |
| Category / type, venue, organizer, performer | **WP taxonomies** (`wp_terms` + `wp_term_taxonomy` + `wp_termmeta`) | reusable across events, filter + free `/organizer/x/` archive + auto count |
| Ticketing — tiers, orders, attendees | **custom tables** (`ticket_types`→`registrations`→`guests`) | high-volume, transactional, typed, `SUM`/`sold<capacity` queries |
| Performer detail | `fevents_performer` term + term meta (photo, designation, social) | **thin** — a name+photo you tag events with, no bio/profile page. Migrate to a CPT only if rich profiles are ever needed. |

**Key identity rule:** `wp_fevents_events.id` is the internal PK all ticketing FKs use
(`event_id`). `wp_fevents_events.post_id` is the 1:1 link to the `wp_posts` row and the
**taxonomy anchor** (`wp_term_relationships.object_id` = that post id). One event = one
`wp_posts` row + one `wp_fevents_events` row.

## 2. Registration (bootstrap)

```php
// The event as a CPT — gives us the page, editor, admin, REST, archive for free.
register_post_type('fevents_event', [
    'public'       => true,
    'has_archive'  => true,                 // /events/
    'rewrite'      => ['slug' => 'events'],
    'supports'     => ['title','editor','thumbnail','author','custom-fields'],
    'show_in_rest' => true,
]);

// Classification — reusable, filterable, browsable, with term meta.
foreach ([
    'fevents_event_type' => ['label' => 'Event Types', 'hierarchical' => true],
    'fevents_venue'      => ['label' => 'Venues',       'hierarchical' => true],
    'fevents_organizer'  => ['label' => 'Organizers',   'hierarchical' => true],
    'fevents_performer'  => ['label' => 'Performers',    'hierarchical' => false], // flat, like tags
] as $tax => $args) {
    register_taxonomy($tax, 'fevents_event', [
        'public'       => true,
        'show_in_rest' => true,
    ] + $args);
}
```

## 3. Data flow — admin creates an event

| # | Writes | Notes |
|---|---|---|
| 1 | `wp_posts` — 1 `fevents_event` row | title / editor body / slug / status=publish / author |
| 2 | `wp_postmeta` — `_thumbnail_id` (cover), `_fevents_form_fields`, `_fevents_settings` | cover = WP media library; config blobs |
| 3 | `wp_term_relationships` — event ↔ event_type / venue / organizer terms | `wp_set_object_terms($post_id, $termIds, $tax)` (assign, M:N) |
| 4 | `wp_fevents_events` — 1 row (`post_id` FK) | typed: start_at, end_at, timezone, venue_type, join_policy, waitlist_enabled, calendar_id, currency, status |
| 5 | `wp_fevents_ticket_types` — N rows (`event_id` = events.id) | tiers |

**Service shape (WPFluent ORM + a transaction at the service layer):**
```php
DB::transaction(function () use ($data) {
    $postId = wp_insert_post([
        'post_type'   => 'fevents_event',
        'post_title'  => sanitize_text_field($data['title']),
        'post_content'=> wp_kses_post($data['description']),
        'post_status' => 'publish',
    ], true);
    // taxonomy assignment (reusable classifiers)
    wp_set_object_terms($postId, (array) $data['organizer_ids'], 'fevents_organizer');
    wp_set_object_terms($postId, (array) $data['venue_ids'],     'fevents_venue');
    wp_set_object_terms($postId, (array) $data['type_ids'],      'fevents_event_type');
    // typed machine data
    Event::create([
        'post_id'          => $postId,
        'status'           => 'active',
        'start_at'         => $data['start_at'],
        'end_at'           => $data['end_at'],
        'timezone'         => $data['timezone'],
        'calendar_id'      => absint($data['calendar_id']),
        'venue_type'       => $data['venue_type'],
        'join_policy'      => $data['join_policy'],
        'waitlist_enabled' => (int) $data['waitlist_enabled'],
    ]);
});
```

## 4. Data flow — create an organizer / venue / category (a term)

An organizer is **not a post** — it's a **term** + term meta. Reusable across events.

| # | Writes |
|---|---|
| 1 | `wp_terms` — the name/slug |
| 2 | `wp_term_taxonomy` — 1 row, `taxonomy='fevents_organizer'`, `count` auto-maintained |
| 3 | `wp_termmeta` — the extra fields |

```php
$term = wp_insert_term($name, 'fevents_organizer');            // wp_terms + wp_term_taxonomy
$tid  = $term['term_id'];
update_term_meta($tid, 'fevents_email',   sanitize_email($email));
update_term_meta($tid, 'fevents_phone',   sanitize_text_field($phone));
update_term_meta($tid, 'fevents_website', esc_url_raw($site));
update_term_meta($tid, 'fevents_logo_id', absint($attachmentId));   // WP media library
```
Term meta keys per taxonomy:
- `fevents_event_type` → `fevents_color`, `fevents_icon_id`
- `fevents_venue` → `fevents_address`, `fevents_city`, `fevents_map_url`, `fevents_lat`, `fevents_lng` *(view-only; no geo query)*
- `fevents_organizer` → `fevents_email`, `fevents_phone`, `fevents_website`, `fevents_social`, `fevents_logo_id`
- `fevents_performer` → `fevents_photo_id`, `fevents_designation`, `fevents_social` *(thin — no bio/profile)*

## 5. Reads

```php
// events by organizer (reusable classifier) — the payoff of taxonomy
new WP_Query(['post_type'=>'fevents_event',
  'tax_query'=>[['taxonomy'=>'fevents_organizer','terms'=>$organizerId]]]);

// a date-range calendar view — hits the typed custom table, not meta
Event::whereBetween('start_at', [$from, $to])->where('status','active')->get();
```
- **Classification / browse / filter / archive** → taxonomy (`WP_Query` `tax_query`, `get_term_link`).
- **Dates, capacity, ticket sums, check-in** → `wp_fevents_events` + ticketing tables (real SQL, indexed).

## 6. What changed from the previous schema

- **Dropped** `wp_fevents_people` (organizer + performer → taxonomies).
- **Event** reshaped into a CPT extension: `title` / `slug` / `description` → `wp_posts`;
  `visibility` → `post_status`; `created_by` → `post_author`; `cover_image_id` →
  `_thumbnail_id`; `form_fields` / `settings` → `wp_postmeta`; `address` / `lat` / `lon`
  → `fevents_venue` term meta. **Added** `post_id` (1:1 FK, unique, taxonomy anchor).
- **Added** the 3 taxonomies + `wp_termmeta` usage (WP-native, not our tables).
- **Dropped** `wp_fevents_sponsors` — not needed now. If sponsors return, they'll be a
  taxonomy (`fevents_sponsor`) like organizer, not a table.

## 7. Guardrails that apply

- Term meta values via `maybe_unserialize` are external → treat per
  [fix-patterns.md](../../rules/fix-patterns.md) (safe unserialize). Sanitize each term-meta
  field on write (`sanitize_email`/`esc_url_raw`/`absint`).
- Event body (`post_content`) rendered through `wp_kses_post`; term/name output escaped
  at the sink ([always.md](../../rules/always.md)).
- The event↔post is 1:1 — deleting the `fevents_event` post cascades the
  `wp_fevents_events` row (`ON DELETE CASCADE`), which cascades ticket_types /
  registrations / guests. A guest's `ticket_type_id` is `RESTRICT` (can't orphan a sold ticket).
