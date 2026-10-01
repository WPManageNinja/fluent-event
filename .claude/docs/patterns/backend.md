# Backend patterns

Reference: `fluent-community/app/`. See [README.md](README.md) for priority order.

---

## Directory map

fcom's layout, which affiliate and booking both broadly share:

```
app/
├── Functions/          global helpers, Utility class
├── Hooks/
│   ├── Handlers/       one class per subsystem, self-registering
│   ├── CLI/            WP-CLI commands
│   ├── actions.php     instantiates handlers
│   └── filters.php
├── Http/
│   ├── Controllers/    thin — validate, delegate, respond
│   ├── Policies/       authorization
│   ├── Requests/       form-request validation (booking uses these; fcom rarely)
│   └── Routes/         api.php, vue.php
├── Models/             persistence, relations, accessors
├── Services/           business logic. Most of the code lives here
└── Views/              PHP templates — admin shell, emails
```

**Services is where the bulk goes.** fluent-booking has 13 controllers and ~20 top-level
services. If a controller is growing past ~40 lines a method, the logic belongs in a
service.

`app/Modules/` (affiliate) is for genuinely pluggable subsystems — Auth, Portal, Tracker.
Not needed in v1; revisit when payments or the fcom bridge arrive.

---

## Controllers

Thin. Validate, delegate, shape the response. No `$wpdb`, no transactions, no business
rules.

fcom's `SpaceController::create()` is the canonical shape:

```php
public function create(Request $request)
{
    $data = $request->get('space', []);            // namespaced payload

    $data['slug']    = sanitize_title($slug, '');  // one line per field
    $data['title']   = sanitize_text_field(Arr::get($data, 'title'));
    $data['privacy'] = sanitize_text_field(Arr::get($data, 'privacy'));

    $this->validate($data, [
        'title'   => 'required',
        'slug'    => 'unique:fcom_spaces,slug',
        'privacy' => 'required|in:public,private,secret'
    ]);

    // …delegate…

    return [...];
}
```

**We take two of those four and diverge on the other two**, following fluent-affiliate and
fluent-booking instead. What we keep:

- **Validation rules carry real constraints** — `unique:fe_calendars,slug`,
  `in:public,draft`. Don't settle for `required|string` when the column has a
  vocabulary.
- **A success response is the returned array**, with `__()` messages; `sendError` is
  kept for failures that need an explicit status.

What we do differently:

- **Payload is flat, not namespaced.** `$request->all()`, not `$request->get('calendar', [])`.
  Affiliate and booking both post a flat bag and so do we.
- **Sanitizing is a field→callback map in a Request class, not a line per field.** fcom's
  verbosity buys nothing once the same fields are needed by both create and update. Ours:

  ```php
  class CalendarRequest extends BaseRequest
  {
      const SANITIZERS = [
          'title'       => Sanitizer::SANITIZE_TEXT_FIELD,
          'slug'        => Sanitizer::SANITIZE_TITLE,
          'description' => Sanitizer::WP_KSES_POST,
      ];
      const RULES = [ /* … */ ];
  }
  ```

  `sanitize()` walks that map and returns **only** keys it knows, which is what makes the
  downstream `Model::create()` safe. That is the mass-assignment defence — an allowlisted
  array, not a hand-written line per field. `->fill($request->all())` stays banned
  ([always.md](../../rules/always.md)).

- **Sanitize before validate, not after.** The uniqueness check has to see the canonical
  slug: validate `"Team Offsite"` first and it passes, then `sanitize_title()` turns it into
  `team-offsite` and the insert hits the unique index. Sanitizers that *destroy* bad input
  rather than degrade it (`sanitize_hex_color` returns `null`) therefore can never 422 —
  pair those with a `nullable` rule and an explicit fallback, and know that the rule is
  documentation rather than a live check.

---

## Services

Business logic, transactions, cross-model writes, hook emission.

- Multi-row writes that must be atomic open `DB::transaction()` **at the service layer**.
  Don't catch inside the closure — that commits half the work.
- A service method's signature must match every caller. Name drift (`$eventId` vs
  `$event_id`) becomes a contract bug.

**When a service is warranted.** Cross-model writes, transactions, or logic with more than
one caller. A single-row CRUD resource does not get one — `CalendarController` writes one
row per action with no transaction and no second writer, so a service there would be a
one-caller passthrough. Chunk 11 writes `wp_posts` and `event_details` together and is where
the layer earns its place.

**The controller emits, never the service (In general, but can exception according to need and ask).** see [hooks-and-filters.md](hooks-and-filters.md).

---

## Models

Thin. Persistence, relationships, scopes, accessors — no business logic.

fcom's `Space` model is almost entirely a `defaultSettings()` array:

```php
public function defaultSettings()
{
    return [
        'layout_style'      => 'timeline',
        'show_sidebar'      => 'yes',
        'members_page_status' => 'members_only', // members_only, everybody, logged_in, admin_only
    ];
}
```

Two things worth copying:

- **Defaults for a JSON config column live on the model**, in one place, not scattered
  across whatever code happens to read them. Name the method after the column, and make it
  `static` so callers can read it without instantiating a model: `event_details.settings` →
  `defaultSettings()`, `calendars.meta` → `defaultMeta()`.
- **Inline comments name the allowed values.** Our `settings` and `status` columns are
  free-form `VARCHAR`; the model is where the real vocabulary gets documented.

Every persisted model declares explicit `$fillable`. Adding a column to it is a security
decision — justify it.

---

## Policies

fcom's `SpacePolicy` shows the two-tier shape:

```php
public function verifyRequest(Request $request)   // fallback for everything
{
    if ($request->getMethod() == 'GET') {
        return !!Helper::canAccessPortal($userId);
    }
    return $this->canManageCommunity($request);
}

public function join(Request $request)            // per-action override
{
    return !!get_current_user_id();
}
```

- `verifyRequest()` is the catch-all, and typically splits on HTTP method — reads are
  permissive, writes are not.
- A method named after the controller action overrides it.
- **Destructive actions must have their own method.** Never let `delete*` / `update*` fall
  through to the fallback ([routes.md](../../rules/routes.md)).
- Policies live in a `withPolicy(...)` route group, not scattered `current_user_can` calls
  in controllers.

---

## Routes

Two files, two purposes:

- **`api.php`** — REST. Grouped by prefix with a policy:

  ```php
  $router->prefix('calendars')->withPolicy('CalendarPolicy')->group(function ($router) {
      $router->get('/', [CalendarController::class, 'getAllCalendars']);
      $router->patch('/{id}', [CalendarController::class, 'updateCalendar'])->int('id');
      $router->delete('/{id}', [CalendarController::class, 'deleteCalendar'])->int('id');
  });
  ```

  **Array callables, not `'Controller@method'` strings** — the class reference survives a
  rename and an IDE can follow it. **Actions read `<verb><Noun>`**, not `index`/`destroy`:
  every method names the resource it touches, which reads better than affiliate's `index`
  once several controllers are open at once. `PATCH` for updates, not `PUT`.

- **`vue.php`** — the SPA's nav and screens. Ours only; no sibling has it.

Constrain every param — `->int('id')`, `->alphaNumDash('slug')`. An unconstrained string
param reaches the handler with arbitrary content.
