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

    return $this->sendSuccess([...]);
}
```

Four things to copy:

- **Payload is namespaced** — `$request->get('event', [])`, not a flat top-level bag. Makes
  the shape obvious on both sides.
- **One line per field.** Verbose on purpose. Never `->fill($request->all())`; mass
  assignment is banned in [always.md](../../rules/always.md).
- **Validation rules carry real constraints** — `unique:wp_fe_calendars,slug`,
  `in:public,private,unlisted`. Don't settle for `required|string` when the column has a
  vocabulary.
- **Responses go through `sendSuccess` / `sendError`**, with `__()` messages.

---

## Services

Business logic, transactions, cross-model writes, hook emission.

- Multi-row writes that must be atomic open `DB::transaction()` **at the service layer**.
  Don't catch inside the closure — that commits half the work.
- The service emits `do_action`, not the controller. See
  [hooks-and-filters.md](hooks-and-filters.md).
- A service method's signature must match every caller. Name drift (`$eventId` vs
  `$event_id`) becomes a contract bug.

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

- **Defaults for a JSON `settings` column live on the model**, in one place, not scattered
  across whatever code happens to read them.
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
      $router->get('/', 'CalendarController@index');
      $router->delete('/{id}', 'CalendarController@destroy')->int('id');
  });
  ```

- **`vue.php`** — the SPA's nav and screens. Ours only; no sibling has it.

Constrain every param — `->int('id')`, `->alphaNumDash('slug')`. An unconstrained string
param reaches the handler with arbitrary content.
