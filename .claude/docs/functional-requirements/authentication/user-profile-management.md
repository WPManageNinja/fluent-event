# FR-2: User Profile & Account Management

**Summary:** After login, every user has a profile area where they view and edit their details, manage account security (password + email changes, both OTP-gated), and deactivate or delete their account.

**Depends on FR-1** — auth, the `wp_fevents_profiles` table, and the verify-by-code (OTP) mechanism (FR-1.4). Sensitive changes here reuse that same code flow. **FR-7** (media archive) handles avatar uploads.

## Approach

- **No new storage.** Profile data lives in `wp_fevents_profiles` (FR-1) + the WP `users`/usermeta tables. This feature is read/write UI over existing tables.
- **OTP for sensitive changes only.** Password and email changes reuse the FR-1.4 code flow (6-digit, HMAC-signed, 10-min, `sessionStorage`-backed). Basic detail edits save directly, no OTP.
- **Account state = FR-1 `status` enum.** Deactivate → `deactivated`, delete → `deleted`. No new lifecycle fields.
- **Avatar via media archive (FR-7).** Avatar uploads go through the media archive (upload → attach); the profile's `avatar` stores the returned `media_key`, not a raw URL.
- **Plugin-owned UI.** Same look/ownership as the rest of the events front end.

## Functional requirements

### FR-2.1 — Edit profile details

- Self-service edit of: **display name**, **username**, **avatar**, **bio**, and other `meta` fields.
- **Avatar upload via media archive (FR-7):** upload returns a `media_key`; saving the profile attaches it (`object_type=user`, `object_source=avatar`) and stores the `media_key` on the profile. Replacing the avatar orphans the old media.
- Username is changeable any time, re-validated for **uniqueness + format**.
- Basic detail edits do **not** require OTP.
- Profile UI is owned by the plugin.

### FR-2.2 — Change password (OTP-gated)

- User opens the security section and enters a new password.
- **Requires OTP:** a code (FR-1.4 mechanism) is sent to the account email and must be verified before the change applies.
- On success: update the WP password (`wp_set_password`), wipe the `sessionStorage` token (FR-1.4), keep the current session valid (don't force re-login).

### FR-2.3 — Change email (OTP-gated)

- User enters a new email address.
- **Requires OTP sent to the *new* email** (proves the user controls it) — verify before applying.
- Notify the **old** email that the address was changed (security alert).
- New email must not already belong to another account.
- On success: update the WP user email; `is_verified` stays 1.

### FR-2.4 — Deactivate account

- Self-deactivate → profile `status = deactivated` (FR-1 status set).
- **Reversible:** the user can reactivate (e.g. on next login, offer "reactivate?").
- While deactivated: hidden from listings/guest lists, cannot join events, no login into the events experience.
- Requires explicit confirmation.

### FR-2.5 — Delete account

- Self-delete → soft-delete / anonymize → profile `status = deleted` (retain for data integrity; strip personal data).
- Requires explicit confirmation (typed confirm or password).
- After deletion: no login, removed from public listings.
- Hard-delete vs anonymize, and whether to gate with OTP — see open questions.

## Acceptance criteria

- User edits display name / username / avatar / bio → saved without OTP; username re-checked for uniqueness.
- User changes password → OTP to account email required; wrong/expired code blocks the change.
- User changes email → OTP to the **new** email required; old email gets a notification; duplicate email rejected.
- User deactivates → account hidden + login blocked; user can reactivate later.
- User deletes → account anonymized, login disabled, removed from listings.

## Open questions

- [ ] Delete: hard-delete the WP user, or anonymize + keep the row? OTP-gate deletion too?
- [ ] Deactivate reactivation: automatic on login, or admin-approved?
- [ ] Does changing email also change the login identifier immediately, and invalidate other sessions?
