# always — universal rules for every edit

Applies to every code edit. Each primitive references a row in
[`fix-patterns.md`](fix-patterns.md) — use that when picking the concrete shape.

## Input handling (PHP)

- Never assign a superglobal wholesale (`$data = $_POST` / `$_GET` / `$_REQUEST`).
  Extract each field: `$x = sanitize_text_field( wp_unslash( $_POST['x'] ?? '' ) )`.
- Order for raw superglobal access is fixed: `wp_unslash` → validate → sanitize.
  Never sanitize before unslash.
- **In framework controllers** input comes through `$this->request->get(...)`,
  `$this->request->getSafe(...)` (sanitizes via callback), or `Arr::get($data, ...)`.
  The framework `Request` is pre-unslashed — `wp_unslash` is for raw superglobals only.
  Still sanitize per field.
- Validate shape at the controller entry: `$this->validate($data, $rules, $messages)`
  (throws a 422 `ValidationException`).
- Sanitizers: `sanitize_text_field`, `absint`/`intval`, `sanitize_email`,
  `sanitize_url`, `wp_kses_post` (rich HTML / markdown — not `esc_html`).

## SQL

- Every dynamic query goes through `$wpdb->prepare('… %d %s', $id, $str)`.
- `LIKE`: `'%' . $wpdb->esc_like($term) . '%'`, then prepare.
- Table/column names interpolated into SQL come from an allowlist or `esc_sql()` —
  never user-controlled.
- Prefer the WPFluent ORM over raw `$wpdb` CRUD in controllers/services.

## Mass assignment

- Explicit `$fillable` on every persisted model. Never `->fill($request->all())` or
  `::create($_POST)`. Extract validated fields. Adding a column to `$fillable` is a
  security decision — justify it. See [controllers-services.md](controllers-services.md).

## Output / XSS (PHP)

- `esc_html` for text, `esc_attr` for attributes, `esc_url` for URLs,
  `wp_kses_post` for rich HTML / markdown.
- Translations on user-influenceable strings use `esc_html__()` / `esc_attr__()` /
  `esc_html_e()` — never raw `__()` / `_e()` (a translation can return arbitrary HTML).
- Save-time sanitize is NOT render-time safe. Re-sanitize at the final sink (REST
  response, email body, template).

## Object injection

- Both `unserialize()` and `maybe_unserialize()` are banned on external data. Use a
  sanctioned safe unserializer (e.g. an `App\Functions\Utility::safeUnserialize()`
  wrapping `is_serialized()` + `['allowed_classes' => false]`, once it exists), or
  prefer `json_decode()` for JSON-shaped data. See [fix-patterns.md](fix-patterns.md).

## Dynamic code execution — BANNED

- No `eval()`, `new Function()`, `create_function()`, or dynamic `include` from a
  variable. Refactor to a registered handler map / data-driven dispatch.

## Crypto / randomness

- Tokens / nonces / OTPs / keys: `wp_generate_password(32, false, false)` or
  `bin2hex(random_bytes(16))`. Never `mt_rand`, `rand`, `uniqid`.
- Signature compare: `hash_equals($expected, $received)`. Never `==` / `===`.
- No MD5 / SHA1 for security operations.

## Conditional-logic discipline

- `else if ('some_string')` is always truthy in PHP — compare explicitly with `===`.
- No `if ($x = foo())` when comparison was intended.
- `&&` binds tighter than `||` — parenthesize compound auth checks:
  `(A && B) || C` ≠ `A && (B || C)`.

## Concurrency

- CAS-style status transitions: atomic `UPDATE … SET status='new' WHERE id=? AND
  status='old'`, then check affected rows.
- Find-or-create needs a unique DB index, or two concurrent calls both insert.

## Transactions

- Multi-row writes that must be atomic open `DB::transaction(function () { ... })` at
  the SERVICE layer. Don't catch the exception inside the closure (it commits half the
  work) — let it bubble.

## Systematic root-cause debugging

- Fix the ROOT cause, not the symptom. Reproduce → trace up the call stack → fix at
  the earliest point bad state originated. Don't swallow errors in `try/catch` or
  guard at the symptom site. Surface-only fix is acceptable only for unfixable
  third-party roots, with a comment naming the upstream issue + removal condition.

**Enforced by** scanner rule 1 (wholesale superglobal assignment). The rest of this
file is self-audit discipline, not hook-blocked.
