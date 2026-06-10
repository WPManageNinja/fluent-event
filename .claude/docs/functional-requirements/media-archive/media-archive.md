# FR-7: Media Archive

**Summary:** A shared media table tracking every upload the plugin manages — event covers & galleries, calendar logos/images, profile avatars, email branding. One archive, referenced by all features, with orphan cleanup.

**Used by FR-2** (avatars), **FR-6** (event cover/gallery), **FR-7+** (calendars), **FR-4.7** (email branding).

## Approach

- **One shared table** `wp_fevents_media` — polymorphic (`object_type` + `object_id`) like `wp_fevents_meta`, so any feature attaches media without its own table.
- **Upload then attach.** A new upload is `status = active` but **unattached** (`object_id` NULL) — an orphan. It becomes owned when saved onto an entity (`object_id` set). A scheduled job prunes long-unattached rows → no junk pile-up.
- **Driver-aware.** `driver` records the backend (`local`, `s3`, …) so storage can move without touching consumers.
- **Reference, don't inline.** Entities store a media reference (id / `media_key`), not raw URLs — one source of truth, easy to swap/cleanup.

## Data model

**Table: `wp_fevents_media`**

| Column                      | Type         | Notes                                                                       |
| --------------------------- | ------------ | --------------------------------------------------------------------------- |
| `id`                        | BIGINT PK    |                                                                             |
| `media_key`                 | VARCHAR      | unique handle used to reference the media                                   |
| `object_type`               | VARCHAR NULL | `event` \| `calendar` \| `user` \| `email` … what it's attached to          |
| `object_id`                 | BIGINT NULL  | the attached entity                                                         |
| `object_source`             | VARCHAR      | semantic slot — `event_cover`, `event_gallery`, `calendar_logo`, `avatar` … |
| `user_id`                   | BIGINT NULL  | uploader                                                                    |
| `status`                    | VARCHAR      | `active` = live · `deleted` = soft-deleted. (Orphan = `active` with `object_id` NULL.) |
| `media_type`                | VARCHAR NULL | mime / category (image, file)                                               |
| `driver`                    | VARCHAR      | storage backend — `local` \| `s3` … (default `local`)                       |
| `media_path`                | TEXT NULL    | storage path                                                                |
| `media_url`                 | TEXT NULL    | public URL                                                                  |
| `settings`                  | JSON NULL    | extra — dimensions, alt text, sizes                                         |
| `created_at` / `updated_at` | TIMESTAMP    |                                                                             |

Indexes: `(object_type, object_id)`, `(media_key)`, `(status)`, `(user_id)`.

## Functional requirements

### FR-7.1 — Upload

- Accept an upload → store via the configured `driver` → create a row with `status = active`, `object_id` NULL (unattached).
- Return `media_key` + URL for the UI to reference.
- Validate type/size; capability-gated where the context requires it.

### FR-7.2 — Attach

- When an entity saves and uses a media item, set `object_type` / `object_id` / `object_source` (now attached).
- Replacing an entity's media → old one's `object_id` cleared (orphan) or `status = deleted`.

### FR-7.3 — Serve & reference

- Entities reference media by `id` / `media_key`; the archive resolves the current URL (driver-aware).

### FR-7.4 — Orphan cleanup

- A scheduled job deletes long-unattached rows (`object_id` NULL) older than a threshold (and their stored files).
- Deleting an entity orphans (or deletes) its media.

## Acceptance criteria

- Upload creates an `active`, unattached row (`object_id` NULL) + returns a reference; the file is not lost if the form is abandoned (it stays unattached until cleanup).
- Saving an entity links object_type/id (attaches it).
- Swapping media leaves the old one unattached or `deleted`, not dangling-referenced.
- Cleanup removes only long-orphaned inactive rows + their files.
