# controllers-services — controllers + services

Applies to controllers and services with DB writes/reads.

## Layering

- **Controllers**: validate input, call a service, shape the response. No business
  logic, no `$wpdb`, no transactions.
- **Services**: business logic, transactions, cross-model writes, emit `do_action`
  for side-effects.
- **Models**: persistence, scopes, accessors, relationships.

Keep direct `$wpdb` CRUD out of controllers/services — go through the ORM.

## Validation

- `$this->validate($data, $rules, $messages)` — throws a 422 `ValidationException`;
  validated data via `$this->validated()`.
- Sample rules: `'title' => 'required'`, `'slug' => 'nullable|string'`.

## Request access

- `$this->request->get('key', 'default')` — single param.
- `$this->request->getSafe('key', 'sanitize_text_field', 'default')` — read + sanitize.
- `$this->request->all()` — full payload; use sparingly (mass-assign risk).
- Avoid raw `$_REQUEST` / `$_POST` in controllers.

## Mass-assign discipline

`Model::create($request->all())` and `->fill($request->all())` are banned. Extract
validated fields explicitly:

```php
$data = $this->validate( $request->all(), [
    'title' => 'required|string',
    'slug'  => 'nullable|string',
] );
Event::create( [
    'title' => sanitize_text_field( $data['title'] ),
    'slug'  => sanitize_title( $data['slug'] ?? '' ),
] );
```

Set ownership/status server-side — never from the payload. Same for `->update()` /
`->save()` with raw request data.

## Transactions

- Multi-row atomic writes: `DB::transaction(function () { ... })` at the service layer.
  Don't catch inside the closure (commits half the work) — let it bubble.

## Idempotency

- Async chunked jobs (Action Scheduler single-actions) must be retry-safe: cursor
  pagination stored on the record, short-TTL `wp_cache` guards for in-flight chunks.
- Webhook/import handlers: dedupe on a stable key so a replay doesn't double-create.

## Response shape

- `$this->sendSuccess([...])` / `$this->sendError([...], $status)`. Match the file's
  existing convention.

## Event emission

- Side-effects go through `do_action('fluent_events/<noun>/<verb>', $args)`.
- Don't emit a hook AND directly call the same effect — listeners fire twice.
- Eloquent lifecycle hooks (`creating`/`deleting`) firing AND a manual `do_action`
  for the same conceptual event = duplicate. Pick one path.

## DRY

- Grep before adding a helper. Three near-identical blocks = extract; two is fine.
- Service method signatures match every caller — name drift (`$eventId` vs
  `$event_id`) becomes a contract bug.

## Self-audit

1. Controller does no business logic / `$wpdb` / transactions?
2. Service opens a transaction for multi-row atomic writes?
3. Mass-assign goes through explicit field extraction?
4. Idempotency story for async jobs / webhooks?
5. Response uses `sendSuccess`/`sendError`?
6. No duplicate event emission?
7. No copy-paste of an existing helper?

**Enforced by** scanner rule 2 (`->fill($request->all())`) and rule 3
(`Model::create()` with a raw request payload).
