# email.md — email / notification correctness

Triggered when editing `app/Hooks/Handlers/EmailNotificationHandler.php`, `app/Services/Libs/Mailer.php`, `app/Services/Libs/DailyDigest.php`, `app/Services/Libs/EmailComposer.php`, `app/Services/Libs/FileSystem.php`, `app/Services/EmailDomBuilder.php`, any new `wp_mail()` callsite, or header build.

## What's actually in the codebase

- **Central handler**: `app/Hooks/Handlers/EmailNotificationHandler.php` (registered in `app/Hooks/actions.php`). Acts as fan-out + chunked async worker via Action Scheduler single-actions. `register()` at line 24, `add_action` calls inside `register()` reach L37.
- **Libs (flat files in `app/Services/Libs/`)**:
  - `EmailComposer.php` — builds email payloads
  - `Mailer.php` — wraps `wp_mail`
  - `DailyDigest.php` — scheduled digest emails
  - `FileSystem.php` — helper
- **DOM builder**: `app/Services/EmailDomBuilder.php` — assembles HTML email body.

**Pro emits zero `wp_mail()` calls directly.** All pro email flows route through free's mailer.

## Chunked async pattern

`EmailNotificationHandler` uses `as_schedule_single_action` heavily to break long recipient lists into chunks:

- `fluent_community/email_notify_new_posts` (L72, L199) — new-post email fan-out
- `fluent_community/comment_added_async` (L219, L231, L246, L357) — comment-notification fan-out
- `fluent_community/email_notify_users_everyone_tag` (L475 + `NotificationEventHandler.php:647`) — @everyone tag, paginated via `last_send_user_id`
- `fluent_community_send_daily_digest` (L563) — daily digest, self-reschedules next chunk

Rules for new async-email flows:
1. **Chunk size**: process ~50-100 recipients per scheduled action. Match existing patterns.
2. **Cursor-based pagination**: store `last_send_user_id` or similar on the booking / feed record. Don't `OFFSET` paginate (skips on insertion).
3. **Re-schedule self if more work remains** (pattern in `maybeSendDailyDigest` at L563).
4. **Group `'fluent-community'`** so deactivation can clean up.
5. **Idempotency**: every chunk must be safe to retry (Action Scheduler will retry on failure).

## Header injection

- `wp_mail` receives headers as an array. Any header value built from user input must strip CR / LF:

```php
$safe = str_replace(["\r", "\n"], '', $userValue);
$headers[] = "Reply-To: {$safe}";
```

- Fix-pattern row: `Email header injection`.
- An attacker injecting `\r\nBcc: attacker@evil.com\r\n` into a Reply-To or From field can BCC themselves on every digest email.

## From / Reply-To

- `From:` should be a configured plugin sender (admin setting). Never raw user input.
- `Reply-To:` may include the originating member's email but only after CR/LF strip + `sanitize_email`.

## To / Recipients

- Recipient list is SERVER-DERIVED from `NotificationSubscription` / `NotificationSubscriber` models. NEVER trust a `to_email` parameter from a request — attacker can spam arbitrary inboxes via your server.
- Build recipient lists from the database, scoped by feed/space/notification subscription state.

## Subject

- CR/LF strip on the subject too.
- Subject contains rendered SmartCodes (`SmartCodeParser`) — sanitize parsed output, not the template.

## Body — escaping

- HTML body via `EmailDomBuilder` — every interpolation runs through `esc_html` / `esc_url` / `wp_kses_post` for rich segments.
- Translation strings use `esc_html__()` for plain-text, `wp_kses_post(__())` for rich.
- Member-supplied content (post body, comment body) — `wp_kses_post` at render-time. Save-time sanitize is NOT enough.

## Confirmation / unsubscribe links

- Build link URLs SERVER-SIDE from the subscription record:

```php
$link = add_query_arg([
    'fcom_action' => 'unsubscribe',
    'sub_uid' => $subscription->uid,
    '_wpnonce' => wp_create_nonce("fcom_unsub_{$subscription->id}"),
], home_url('/'));
```

- Resource-bound nonce (subscription ID is in the nonce action string). Public-nonce-on-user-chosen-ID rule applies (see `routes.md`).
- Never accept the URL from the request and forward it in the email.

## Logging

- Don't log the full email body to `error_log` or DB. May contain PII.
- Log only metadata: recipient hash, subject prefix, status, action-scheduler action ID.

## Rate-limit on send

- The chunked async pattern provides natural rate-limiting (Action Scheduler runs ~1 batch per cron tick).
- For bulk send (e.g., @everyone tag on a 10k-member space), the existing pagination handles this. Don't introduce a blocking `foreach ($users as $u) wp_mail(...)` — will hit `max_execution_time`.

## Self-audit

For each email-related change:
1. New async fan-out uses Action Scheduler single-action with cursor pagination?
2. Group `'fluent-community'` on `as_schedule_single_action`?
3. Every header value built from user input runs CR/LF strip?
4. Recipient list derived server-side, not from request?
5. Confirmation / unsubscribe links built server-side with resource-bound nonces?
6. HTML body fields run through `wp_kses_post` at render (not save)?
7. No full body logged?
8. Idempotent chunked retry?
