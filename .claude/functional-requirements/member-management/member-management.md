# FR-3: Member Management

**Summary:** Users with member-edit permission (host / admins / editors granted the capability) view and manage the member list — sourced from profiles. The list is filterable by status tabs, viewable as cards or a table, and sortable. Managers can change a member's status. Blocked or suspended members are locked out of the portal with a warning.

**Depends on FR-1** — members are `wp_fevents_profiles` rows; status uses the FR-1 `status` enum; the portal gate (FR-1.3) enforces blocked/suspended.

## Approach

- **No new storage.** Members are `wp_fevents_profiles` rows (FR-1). This feature is list / filter / sort / edit UI over that table.
- **Access by manager permission.** The member-management screen is gated by the **member-management permission**, held by the WP-admin tier (`manage_options`) and by any user whose assigned **manager role** includes it (roles assigned in FR-4.4, stored in `wp_fevents_meta`). Normal members do not qualify. (Exact role→permission model is the open manager-model question — see FR-4.)
- **Status drives everything.** Tabs, enforcement, and edits all read/write the FR-1 `status` enum (`active`, `pending`, `invited`, `suspended`, `blocked`, `deactivated`, `deleted`).
- **Enforcement at the gate.** Portal access (FR-1.3) checks status on entry; `blocked`/`suspended` → warning screen, no entry. No separate enforcement path.
- **Settings live in `wp_fevents_meta`.** Permission mappings + member-management config are section rows in the generic `wp_fevents_meta` table (see FR-4). Per-user manager roles are `object_type='user'` rows there too.

## Functional requirements

### FR-3.1 — View members list (permission-gated)

- Only users with the **member-management permission** (admin tier, or a manager role that grants it — FR-4.4) can open the member-management screen.
- Lists members from `wp_fevents_profiles` with key fields: avatar, display name, username, email, status, joining date.
- Paginated; supports search by name / username / email.

### FR-3.2 — Status tabs

- Tabs filter the list by status: **All** (default), **Active**, **Pending**, **Invited**, **Suspended**, **Blocked**, **Deactivated**.
- Each tab shows a count.
- (`deleted` is hidden by default — surfaced only via an explicit filter.)

### FR-3.3 — Card & table views

- Each member renders as a **card** in a grid, or a row in a **table** — user toggles between the two.
- Card shows avatar, name, username, status badge, joining date, quick actions.

### FR-3.4 — Sort

- Sort by **name**, **joining date** (`created_at`), **status**, and **last update**.
- Ascending / descending toggle. Default: joining date, newest first.

### FR-3.5 — Update member status

- A manager (admin tier, or a role granting the member-management permission) can change a member's status — e.g. `active`, `pending`, `suspended`, `blocked`, reactivate a `deactivated` one.
- Status change is logged (who changed, when) and may notify the member by email (open question).
- Guardrails: a manager cannot block the owner/themselves; only higher-capability users can act on other managers (open question).

### FR-3.6 — Enforce blocked / suspended

- A `blocked` or `suspended` member attempting to access the portal sees a **warning message** and is denied entry (ties into the FR-1.3 gate).
  - `suspended` → temporary; message indicates it may be lifted.
  - `blocked` → permanent; message is final.
- Their session is invalidated; they cannot join events or view member-only content while in that state.

## Acceptance criteria

- User without the member-management permission → cannot open the member screen.
- Manager opens the screen → sees all members, default **All** tab, sorted by newest joining date.
- Switching tabs filters by status with correct counts.
- Toggling card/table re-renders the same data in the other layout.
- Sorting by name / joining date reorders the list.
- Manager sets a member to `blocked` → that member, on next portal access, gets the warning and is locked out.
- Setting a `suspended` member back to `active` → member regains access.

## Open questions

- [ ] Which manager role(s) grant the member-management permission by default (tied to the FR-4 manager-model decision).
- [ ] Notify the member by email when their status changes?
- [ ] Manager-on-manager actions: can an editor block an admin? Define the hierarchy.
- [ ] Suspended duration — manual lift only, or a timed auto-expiry?
- [ ] Bulk actions (select many → change status) in v1, or later?

---

## Note — settings & permissions storage

Settings + permission mappings live in the generic **`wp_fevents_meta`** table (one row per section), and per-user manager roles as `object_type='user'` rows. Full storage spec is in **FR-4** ([settings.md](../settings/settings.md)). Enforcement of management actions uses `current_user_can()` / the assigned plugin role.
