# email — mail correctness + header injection

Applies to `wp_mail()` callers and any mail-header build.

## Header injection

- Any header value (`From` / `Reply-To` / `Cc` / `Bcc` / `To` / `Subject`) built from
  dynamic input strips CR/LF first:
  `$safe = str_replace(["\r", "\n"], '', $value);`
  A `\r\nBcc: attacker@evil` injection BCCs an attacker on every send.

## Recipients + From

- `From:` is a configured plugin sender — never raw user input.
- Recipient lists are SERVER-DERIVED (from subscription / guest models). Never trust a
  `to_email` from the request — that turns your server into a spam relay.

## Body

- HTML body interpolations run through `esc_html` / `esc_url` / `wp_kses_post` at render
  time (save-time sanitize is not enough). Translations use `esc_html__()`.

## Async fan-out

- Bulk send is chunked via Action Scheduler (cursor pagination, group `fluent-events`),
  never a blocking `foreach (...) wp_mail()`. Each chunk is idempotent on retry.

**Enforced by** scanner rule 13 (mail-header literal present, no CR/LF strip in the file).
