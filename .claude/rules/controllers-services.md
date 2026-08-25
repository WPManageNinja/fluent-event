# controllers-services — controllers + services

Applies to controllers and services with DB writes/reads.

## Layering

- **Controllers**: validate input, perform or delegate the write, emit the `do_action`
  hooks if needed, return the payload. No `$wpdb`.
- **Services**: logic with more than one caller, or a job with no controller on the
  stack. No `do_action` unless explicitly asked.
- **Models**: persistence, scopes, accessors, relationships.

A service is earned through **reuse**, not through the kind of work it does. A write with
one caller belongs in its controller even when it opens a transaction — `EventController`
wraps the `wp_posts` insert and the `event_details` row in one `DB::transaction` because
nothing else creates an event. Extract to a service when a second caller appears, or when
the body outgrows the action around it.

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

- Multi-row atomic writes open `DB::transaction(function () { ... })` — in the service
  when one exists, otherwise in the controller action doing the write.
- Don't catch inside the closure (commits half the work) — let it bubble.

## Idempotency

- Async chunked jobs (Action Scheduler single-actions) must be retry-safe: cursor
  pagination stored on the record, short-TTL `wp_cache` guards for in-flight chunks.
- Webhook/import handlers: dedupe on a stable key so a replay doesn't double-create.

## Response shape

- **Success: `return` the payload array.** The framework hands a controller's return
  value straight to WP, which wraps an array in a `WP_REST_Response` at 200 — exactly
  what `sendSuccess()` built by hand. The wrapper added a call and a nesting level and
  bought nothing.

```php
return [
    'message' => __('Event has been successfully created', 'fluent-event'),
    'event'   => $event,
];
```

- **Failure still goes through `$this->sendError([...], $status)`**, because a non-200
  status has to be set explicitly. Validation throws instead — `$this->validate()`
  raises a `ValidationException` the router renders as a 422.

## Event emission

- Side-effects go through `do_action('fluent_event/<noun>/<verb>', $args)`.
- **Emit from the CONTROLLER generally, not the service.** The controller owns the request
  lifecycle, so one read of the action tells you every hook that request fires. A
  service is called from more than one place (another service, a cleanup handler, a
  scheduled job); emitting inside it means the same hook fires from paths that never
  intended it, and the duplicate is invisible at both callsites.

```php
// GOOD — controller
$guest = GuestService::createGuest($event, $data);
do_action('fluent_event/guest/created', $guest);

// Delete brackets the service call:
do_action('fluent_event/guest/before_deleted', $guest);
GuestService::deleteGuest($guest);
do_action('fluent_event/guest/deleted', $guest);
```

- **The exception is a path with no controller** — an Action Scheduler job or a
  cascade cleanup running under a WP action. There the handler or the job emits,
  because no controller is on the stack. `CleanupHandler` and `EventReminderService`
  are the sanctioned cases; don't grow the list to dodge the rule.
- Don't emit a hook AND directly call the same effect — listeners fire twice.
- Eloquent lifecycle hooks (`creating`/`deleting`) firing AND a manual `do_action`
  for the same conceptual event = duplicate. Pick one path.

## DRY

- Grep before adding a helper. Three near-identical blocks = extract; two is fine.
- Service method signatures match every caller — name drift (`$eventId` vs
  `$event_id`) becomes a contract bug.

## Self-audit

1. Controller does no business logic / `$wpdb` / transactions?
2. `do_action` emitted from the controller, not the service (unless there is no
   controller on the stack)?
3. Service opens a transaction for multi-row atomic writes?
4. Mass-assign goes through explicit field extraction?
5. Idempotency story for async jobs / webhooks?
6. Success returns the payload array directly; failures use `sendError`?
7. No duplicate event emission?
8. No copy-paste of an existing helper?

**Enforced by** scanner rule 2 (`->fill($request->all())`) and rule 3
(`Model::create()` with a raw request payload).
