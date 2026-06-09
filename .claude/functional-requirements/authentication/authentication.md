# FR-1: Authentication

**Summary:** Visitors log in or sign up directly from any event. Auth runs on WordPress core; identity is the WordPress `users` table. A plugin-owned `wp_fevents_profiles` table holds everything else (1:1 per user). Joinees with no account are auto-registered and confirmed by email code.

> Profile **editing** and account management (change name/password/email, deactivate, delete, my-events timeline) are specified separately in [user-profile-management.md](user-profile-management.md) (FR-2). This file owns identity, sign-in, verification, and the profile **table**.

## Approach

- **Auth:** WordPress core (`wp_insert_user`, `wp_authenticate`, `wp_set_auth_cookie`). No separate password or session store.
- **Identity:** one person = one WordPress user. Never duplicated.
- **Profile:** `wp_fevents_profiles`, one row per user — handle, display name, avatar, status, verification, JSON `meta`. Owned by this plugin.
- **Join:** no separate register step — submitting a join creates the account and verifies email by code.

## Data model

**Table: `wp_fevents_profiles`** — one row per WordPress user.

| Column                      | Type                               | Notes                                          |
| --------------------------- | ---------------------------------- | ---------------------------------------------- |
| `id`                        | BIGINT PK                          | auto-increment                                 |
| `user_id`                   | BIGINT, UNIQUE                     | FK → WP `users.ID` (1:1)                       |
| `username`                  | VARCHAR                            | unique public handle / profile slug            |
| `display_name`              | VARCHAR                            | shown across events/calendars                  |
| `avatar`                    | TEXT                               | URL; fallback to gravatar / generated initials |
| `status`                    | ENUM (see below)                   | account state                                  |
| `is_verified`               | TINYINT                            | email-verified flag                            |
| `meta`                      | LONGTEXT (JSON)                    | extensible — bio, cover photo, social links, prefs |
| `created_at` / `updated_at` | TIMESTAMP                          |                                                |

**`status` values:**

| Value | Meaning |
|-------|---------|
| `active` | Verified, normal access. |
| `pending` | Created, awaiting email verification (auto-registered joinee or fresh signup). |
| `invited` | Stub created via invite/import; not yet claimed by the person. |
| `suspended` | Temporary admin/host block; reversible. |
| `blocked` | Permanent ban by admin. |
| `deactivated` | Self-deactivated; can be reactivated by the user. |
| `deleted` | Soft-deleted / anonymized; retained for data integrity, no login. |

The profile row is created/synced whenever a WordPress user first interacts with Fluent Events (signup, join, or first login).

## Functional requirements

### FR-1.1 — Sign up by clicking the signup button

- Enter email + password + name + **username** from the events UI.
- User picks their own username; field is pre-filled with a suggested handle (email local-part → name parts → numeric suffix) they can change.
- System validates the username is unique + valid, then creates a WordPress user and a matching `wp_fevents_profiles` row.
- Confirm via email code (see FR-1.4) before access is granted.
- On success, set a WP auth cookie — logged in site-wide.

### FR-1.2 — Sign up while joining an event

- Joinee submits a join with an email that has **no** account.
- System auto-creates the user + profile behind the scenes, `status = pending`.
- Confirm via email code (see FR-1.4); the join finalizes only after verification.
- If the email **already** has an account → no duplicate; route the person to log in.

### FR-1.3 — Log in

- Enter email/username + password from the events UI.
- Authenticate against WordPress; on success set a WP auth cookie (site-wide).
- **Events access requires a profile** (flat rule). A WP user with no `wp_fevents_profiles` row (e.g. created by another plugin) is not yet a participant.
- Instead of a dead-end block, route them to a **profile-setup page**. The WP account already exists, so this is a one-step profile creation (pick username, verify email if not already verified) — not a new-account register. On completion the profile is created and they enter.
- This applies to **everyone except administrators** — members, moderators, editors, etc. must set up a profile. **Administrators** (`manage_options`) are the exception: they get a profile auto-provisioned on first access (see FR-1.9), so management is never locked out.
- Failed attempts return a generic error ("email or password is incorrect") to block user enumeration.
- **Unverified-account login:** if credentials are correct but the account is still `pending`/unverified (e.g. user closed the tab mid-signup), do **not** dead-end. Issue a **fresh** code and route straight to the verification screen (FR-1.4) instead of completing login.

### FR-1.4 — Verify email by code

- Send a one-time **6-digit** code to the email via `wp_mail()` (delivered through FluentSMTP).
- Code is single-use and expires in **10 minutes**.
- Carried as a hashed, HMAC-signed token — no plaintext stored, no extra DB table (stateless; the token round-trips, the server keeps nothing).
- Wrong or expired code → clear error + **resend** option (rate-limited).

**Persistence across refresh (sessionStorage):**

- On code send, the signed token is stashed in `sessionStorage`. The token is safe to store there — HMAC-signed (unforgeable), email is not secret, code is hashed, expiry is baked in.
- **While a live token is present in `sessionStorage`, show the verification-code UI** (the code-entry screen), not the signup form — mirrors the Fluent Community flow.
- **On page refresh:** if the stored token is still alive (not past its 10-min expiry), re-hydrate and show the verification screen again. If expired/absent, fall back to the signup form.
- **"Log in" link clears the session:** clicking it removes the token from `sessionStorage` and navigates to the login page (abandons the pending verification).
- **Wipe on success:** on successful code verification — from **signup** (FR-1.1/1.2), **password reset** (FR-1.5), or a profile OTP (FR-2) — remove the token from `sessionStorage`.
- **Closed tab → login resends:** closing the tab clears `sessionStorage`, but the account may still be `pending`. If that user later tries to log in, a fresh code is issued and they're routed back to the verification screen (see FR-1.3) — no manual "resend" needed.

### FR-1.5 — Forgot / reset password

- A **"Forgot password?"** link on the login screen starts recovery.
- User enters their email → system sends a **code** (same 6-digit, HMAC-signed, 10-min, `sessionStorage`-backed mechanism as FR-1.4) via `wp_mail()` / FluentSMTP.
- Response is generic whether or not the email exists ("if that email exists, a code was sent") — no enumeration.
- User enters the code on the verification screen, then sets a new password.
- On success: update the WP password (`wp_set_password`), **wipe the `sessionStorage` token**, route to login.
- Rate-limited + nonce-protected (FR-1.8).

### FR-1.6 — Auto-login when a host accepts a request

- Host/manager (with `fevents_manage_guests`) accepts a join request → joinee gets a single-use **signed link**.
- Opening the link logs them in automatically — no manual credentials.
- Allowed only for low-privilege (Member) accounts.

### FR-1.7 — Join via invitation link (host-initiated)

Host/manager invites a guest — by **email** (specific guest) or a **shareable link** (open). Clicking *Accept*:

- **Email-bound invite** (tied to the invitee's address):
  - No account → **auto-create** user + profile, **auto-login**, **no code** needed. The invite was delivered to that email, which already proves ownership → `is_verified = 1`, `status = active`.
  - Existing account → log in (prompt if not already) and attach to the event.
- **Shareable / open link** (not tied to an email):
  - Invitee provides an email → goes through normal **verify-by-code** (FR-1.4) before account + profile creation. Cannot skip the code — ownership isn't yet proven.
- On accept, the invitee is attached to the target event/calendar.
- The invitation token is **single-use + expiring**, validated server-side (same HMAC-signed approach as FR-1.4, or a stored invite record — see open questions).

### FR-1.8 — Security & abuse controls

- Login, signup, and code send/resend endpoints rate-limited per IP (≈10 / 5 min).
- All auth requests nonce-protected.
- No user enumeration via error messages or response timing.

### FR-1.9 — Profile provisioning & data lifecycle

**Provisioning — administrators are auto-created; everyone else self-creates:**

- **Administrators** (WP `manage_options` capability) = the owner tier. Their profile is **lazily provisioned** the first time they open the events admin (has profile? enter : create then enter). Lazy, not seeded — so any admin, including one added or promoted later, is provisioned on first visit, with no migration to re-run on upgrade.
- **Everyone else** (editors, authors, subscribers, members, guests) → no auto-provision. Each creates their own profile via signup (FR-1.1), join (FR-1.2), or the profile-setup route (FR-1.3) before entering.
- Check capability (`current_user_can('manage_options')`), not the role-name string — survives custom/renamed roles.

**Schema migration — version-gated + idempotent:**

- A `FEVENTS_DB_VERSION` constant gates migrations.
- Run the migrator from **both** the activation hook (fresh install) **and** a `plugins_loaded` check (`version_compare(stored, current) < 0` → migrate + bump stored version). The `plugins_loaded` path is what catches **upgrades** — the activation hook does not fire on update.
- Migrations are additive `dbDelta` (create/alter only, never drop); safe to run repeatedly.

**Destruction policy:**

| Event | Effect on data |
|-------|----------------|
| **Deactivate** | No data touched. Only unschedule cron jobs + flush cache. |
| **Upgrade** | No data touched. Additive migration only. |
| **Uninstall** | Data kept by default. Drop `wp_fevents_profiles` only behind an explicit opt-in setting ("delete data on uninstall", default off). |

## Acceptance criteria

- Logged-in WP user **with** a profile opens an event → recognized, no login prompt.
- Logged-in non-admin **without** a profile (Woo customer, editor, etc.) → routed to one-step profile setup (no new WP account).
- Administrator without a profile → profile auto-provisioned on first access; enters directly, no setup gate.
- New visitor signs up from an event → WP account + profile created, verification code emailed, access gated until code entered correctly.
- New email joins an event → account auto-created (`pending`), code emailed, join finalized only after verification.
- Wrong/expired code → clear error + working resend (rate-limited).
- Host accepts a request → joinee opens the acceptance link and is logged in automatically.
- Same email cannot produce two accounts.
- Brute-force attempts hit the rate limit and are blocked.
- Email-bound invite → clicking Accept creates account + profile and logs in, no code prompted.
- Open shareable-link invite → invitee must verify by code before the account is created.
- Forgot password → code emailed (generic response), code verified, new password set, session token wiped.
- User closes tab on the verification screen, later logs in → fresh code emailed automatically, routed back to verification (no dead-end).
- Successful verification (signup or reset) → `sessionStorage` token is gone.

## Open questions

- [ ] Verification code: confirm 6-digit numeric + 10-min TTL as defaults.
- [ ] Auto-login link lifetime + hardening (single-use, privilege ceiling, IP binding?).
- [ ] Is password required at signup, or support passwordless (code-only) accounts?
- [ ] Resend cooldown + max attempts before lockout.
- [ ] Profile table name/prefix and which `meta` fields ship in v1.
- [ ] Invitation token mechanism: stateless HMAC-signed (like FR-1.4) vs a stored `invitations` table (track sent/accepted/expired). Email-bound + open-link both covered?
- [ ] After a password reset: route to login, or auto-login the user straight away?
