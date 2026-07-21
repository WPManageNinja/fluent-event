# FR-7: Media (WordPress media library)

**Summary:** All plugin imagery — event covers/galleries, calendar logos/covers, email branding — uses the **WordPress media library**. There is **no custom media table**; image columns store a WP **attachment ID** (`wp_posts`, `post_type='attachment'`). WP owns storage, sizes, and delivery.

**Used by FR-6** (event cover/gallery), **FR-8** (calendars), **FR-4.7** (email branding).

## Approach

- **Event cover: store an attachment ID, not a URL.** The event cover uses `_thumbnail_id` (WP featured image on the `fevents_event` post) — a `wp_posts` attachment ID. WP owns the file, its metadata (`wp_postmeta`: `_wp_attached_file`, `_wp_attachment_metadata`, `_wp_attachment_image_alt`), and the bytes (`wp-content/uploads/YYYY/MM/`).
- **Calendar logo/cover: store the URL directly.** `calendars.logo_url` / `cover_url` are plain `VARCHAR` URLs — no attachment FK. A calendar's imagery is often external/branding, so the URL is enough; no media-library coupling.
- **WP does the heavy lifting** — thumbnail sizes, `srcset`, alt text, and s3/CDN offload (via WP Offload Media / Smush) come free; no custom `driver`/storage layer.
- **Low volume justifies it.** A cover per event, a logo/cover per calendar, email branding — a handful of attachments. `wp_posts` bloat is negligible, so a custom media table isn't worth the code. (Contrast: fcom is fully custom for feed/avatar scale; fluent-cart uses the WP library for product images and a custom driver only for gated downloads — we have no gated files, so WP library all the way.)

## Upload → store → render

- **Pick / upload** — a host/manager (WP user with `upload_files`) picks via the `wp.media` modal, or the SPA POSTs the file to core `POST /wp/v2/media`, which creates the attachment and returns its `id`. Anonymous guests never upload.
- **Programmatic** — `media_handle_upload()` (does `wp_handle_upload` + `wp_insert_attachment` + `wp_generate_attachment_metadata`) returns the id.
- **Validate on save** — the stored id runs through `absint()` and must be a real attachment: reject if `get_post_type( $id ) !== 'attachment'` (stops pointing a column at an arbitrary post).
- **Render** — resolve at the sink: `wp_get_attachment_image_url( $id, 'large' )` / `wp_get_attachment_image( $id, 'medium', false, [ 'alt' => … ] )`. REST responses send the resolved, `esc_url()`'d URL — not the raw id.

## Lifecycle

- **Ownership** — the attachment belongs to WP; our column only references it. DB-level `ON DELETE SET NULL` (see diagram) plus a render-time guard for a missing/trashed attachment (resolve-or-fallback).
- **Deleting an entity** — decide per case: leave the attachment (reusable — safe default) or `wp_delete_attachment( $id, true )` when the image is exclusively entity-owned.

## Acceptance criteria

- Picking/uploading an image yields an attachment id stored on the entity; the file lives in the WP media library.
- A saved id is validated as an `attachment` before it persists.
- Rendering resolves the id → sized URL via WP helpers; a trashed/missing attachment falls back gracefully — no broken reference.
- Deleting an entity leaves no dangling FK (`SET NULL`) and does not delete a shared attachment unless it is entity-owned.
