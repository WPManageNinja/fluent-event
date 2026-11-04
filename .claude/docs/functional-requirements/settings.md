# FR-4: Settings & Configuration

**Summary:** A central settings page where the host/admin/manager(with full permission) configures everything: general settings, customization, feature addons, managers, access management, privacy, and email templates. Each area is a section in one settings screen.

**Depends on** the product model (single-tenant, WP users + roles + capabilities). Identity is WordPress users; there is no custom auth/profile layer.

## Approach

**Two stores, split by _when_ a value is read** (mirrors fcom):

- **Bootstrap settings → `wp_options` (autoloaded).** Values read very early, on nearly every request, _before_ the plugin ORM is booted:
  - `fe_general` — portal **slug**, **access level**, site **title**, **logos**, **logo permalink** (small + hot).
  - `FE_DB_VERSION` — the migrator reads it before tables are guaranteed to exist.
  - Why `wp_options`: `get_option()` is available immediately (no ORM), autoloaded (already in memory, zero extra query), and survives even if custom tables are missing.
- **Everything else → `wp_fe_meta`** (generic polymorphic store: `object_type`, `object_id`, `meta_key`, `value`). **No mega-blob.**

In `wp_fe_meta`:

- **One row per settings section** — `object_type = 'option'`, one row each: `customization`, `addons`, `access`, `privacy`, `emails`. Editing one section never rewrites another → no write-clobber races. Not autoloaded → no bloat. Cached helper.
- **Same table holds per-user meta** (`object_type = 'user'`): manager roles (`_fe_roles`), user prefs. Per-user meta is for WP users (managers) only.
- **Managers stored per-user** there — assignments travel with the user and are queryable. Sits alongside the product-model WP-admin tier: WP admins (`manage_options`) are always full managers; the host promotes additional users here.
- **Email templates** = the `emails` section row (one entry per system email).
- **Per-object settings live on the object.** Per-calendar / per-event settings are a `settings` JSON column on those entities (not here). This screen is global config only.
- **Access gated** by `manage_options` (host/admin) — only the owner tier configures settings.

**`wp_fe_meta` schema:**

| Column                      | Type                                 | Notes                                                |
| --------------------------- | ------------------------------------ | ---------------------------------------------------- |
| `id`                        | BIGINT PK                            | auto-increment                                       |
| `object_type`               | VARCHAR                              | `option` \| `user` \| `event` \| `calendar` …        |
| `object_id`                 | BIGINT NULL                          | user_id / event_id; `NULL` for options               |
| `meta_key`                  | VARCHAR                              | section name (`general`…) or meta key (`auth_hash`…) |
| `value`                     | LONGTEXT                             | JSON / serialized                                    |
| `created_at` / `updated_at` | TIMESTAMP                            |                                                      |
| INDEX                       | `(object_type, object_id, meta_key)` | lookup                                               |

## Functional requirements

### FR-4.1 — General settings

- **Site/portal title.**
- **Logo** — separate **light** and **dark** variants.
- **Logo permalink** — where clicking the logo goes (default / custom URL).
- **Enable landing pages** — off by default. Off registers no landing routes, so every portal URL 404s, and hides the portal slug field. Only site administrators can change it.
- **Portal slug** — where the public calendars page lives, default `events`. With FluentCommunity mounted at the site root, `/events` is its Events tab, so pick another slug there. Left empty, the portal sits at the site root: the home page becomes the calendars list, and `/{calendar}/` and `/{calendar}/{event}/` open only where no page or post already has that address. Only site administrators can change it. `FLUENT_EVENT_PORTAL_SLUG` overrides it and locks the field, like FluentCommunity's constant.
- **Access level** — who can access the portal: `public`, `logged-in only`, or `role-based` (pick roles).
- _(Expandable — more general keys added later.)_

### FR-4.2 — Customization

- **Enable dark mode** (and/or follow system).
- **Event default layout** — default view for event listings.
- **Guest list default layout** — card grid vs table for an event's guest list.
- Other UI defaults (theme accent, density, etc.) — expandable.

### FR-4.3 — Feature addons

- Toggle plugin **modules** on/off (e.g. calendars, insights, payments).
- Enable **integrations** with other plugins (FluentSMTP, FluentCRM, payment gateways, etc.).
- Each addon: enabled flag + its own config (keys, options) where needed.

### FR-4.4 — Managers

- Host assigns specific users as **managers**, each with a **role** — mirrors fcom (e.g. `Admin`, `Moderator`), where a role is a bundle of permissions.
- Stored per-user (`_fe_roles` meta), not in the settings blob.
- WP administrators (`manage_options`) are always managers implicitly; this screen promotes **additional** non-admin users.
- Removing a manager revokes the role.

### FR-4.5 — Access management

- Control whether **other plugins** can read/use  data (API / data-exposure toggles).
- Allow-list which integrations may access what (e.g. CRM may read members; a third-party may read public events only).
- Default: closed — nothing external reads our data until explicitly allowed.

### FR-4.6 — Privacy settings

- **Who can do what** — visibility + capability rules: who can manage events, who can see guest lists, event visibility defaults, etc.
- Maps to capabilities / roles where it concerns management actions.

### FR-4.7 — Email settings

- Every system email is **customizable**: subject + body, with merge tags (guest name, event, link, etc.), and an enable/disable toggle per email. Sent via `wp_mail()` (FluentSMTP).
- Covered emails (at least):
  - **Event invitation** (FR-6.7)
  - **Join request** received / accepted / declined (FR-6.9)
  - **Event reminder** (date approaching)
  - **Event updated / cancelled**
  - **Payment receipt** (paid events)
  - **Manager role changed** (FR-4.4)
- A shared email **template/branding** (logo, colors, footer) wraps all of them — mirrors fcom's `email.template`.

→ Full email catalog + per-email config: [email-settings.md](email-settings.md).

## Acceptance criteria

- Only `manage_options` users can open settings.
- General: setting light/dark logos + title + access level persists and applies across the portal.
- Customization: changing the guest-list default layout flips its default view.
- Addons: disabling a module hides its UI + endpoints.
- Managers: promoting a non-admin user grants their role; they gain the matching management screens; removing it revokes access.
- Access management: an external plugin cannot read events data unless explicitly allow-listed.
- Email: editing the "event invitation" subject/body changes what invitees receive; disabling an email stops it sending.

## Open questions

- [ ] Manager roles: reconcile fcom-style per-user roles with the product model's "WP roles + capabilities." One model or both? Which role names + capability bundles ship in v1?
- [ ] Access management mechanism: REST API keys, capability filters, or per-integration allow-list? What granularity?
- [ ] Email editor: plain subject/body + merge tags, or a richer (block/HTML) editor? Per-email or shared template only?
- [ ] Settings storage: two-store split. Bootstrap `fe_general` + `FE_DB_VERSION` in `wp_options` (autoloaded, pre-ORM); all other sections + per-user meta in the generic `wp_fe_meta` table, one row per section (no mega-blob, not autoloaded).
- [ ] Privacy vs Access overlap — are these one section or two?
