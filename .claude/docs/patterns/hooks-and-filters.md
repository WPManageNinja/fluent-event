# Hooks and filters

Reference: `fluent-community/app/Hooks/`. See [README.md](README.md) for priority order.

---

## Handlers register themselves

fcom's `app/Hooks/actions.php` does nothing but instantiate:

```php
(new \FluentCommunity\App\Hooks\Handlers\PortalHandler())->register();
(new \FluentCommunity\App\Hooks\Handlers\NotificationEventHandler())->register();
(new \FluentCommunity\App\Hooks\Handlers\CleanupHandler())->register();
```

Each handler owns a `register()` method that wires **its own** `add_action` / `add_filter`
calls. Nothing else knows which hooks a subsystem listens to.

Why this beats a long list of `add_action` lines in one bootstrap file: the wiring lives
next to the code it wires, deleting a handler removes its hooks with it, and `actions.php`
stays a readable table of contents.

One handler per subsystem. `CleanupHandler`, `RateLimitHandler`, `NotificationEventHandler`
— named for what they own, not for the hook they attach to.

---

## Naming

**Actions — `fluent_event/<noun>/<verb>`.** Past tense for things that happened:

```
fluent_event/calendar/created
fluent_event/calendar/updated
fluent_event/event/deleted
fluent_event/guest/rsvp_recorded
```

fcom's real ones: `space/joined`, `space/updated`, `space/user_left`,
`space/member/role_updated`. Nesting goes deeper than two segments when the subject is
nested — copy that rather than flattening to `space_member_role_updated`.

**Before-variants get an explicit prefix** — fcom uses `lesson/before_deleted`,
`section/before_deleted`. Use these when a listener needs the row while it still exists.

### Shipped — calendars

The set `CalendarController` emits, and the shape every other resource should copy:

```php
fluent_event/calendar/created         // $calendar — after insert
fluent_event/calendar/updated         // $calendar — after save
fluent_event/calendar/before_deleted  // $calendar — row still exists
fluent_event/calendar/deleted         // $calendar — row is gone, model still in memory
```

**Pass the model, never a bare id** — including on `deleted`. The row is gone by then, so a
listener handed an id has nothing left to look up; the in-memory model is the only chance it
gets to see what was removed. A `deleted` hook taking `$id` while its three siblings take
`$calendar` also forces every listener to special-case one of the four.

**Emit `before_deleted` and `deleted` as a pair** whenever a delete has listeners worth
serving — the first for work needing related rows still present, the second for cleanup.

**Filters — `fluent_event/<thing>`,** named for the value being filtered, not an event:

```
fluent_event/can_manage_calendar
fluent_event/main_menu_items
fluent_event/default_event_cover
```

fcom's: `can_access_portal`, `main_menu_items`, `default_avatar`, `user/permissions`.

A filter answering a yes/no question reads `can_*`. One returning a list reads as a plural
noun.

---

## Where hooks get emitted

**Whoever performs the write emits.** The failure this guards against is a second caller —
CLI, importer, another service — going through a path that never fires the hook. So where a
service owns the write, the service emits, and a controller that duplicates it is a bug.

Where the controller *is* the only writer, it emits. A single-row CRUD resource with no
transaction and no cross-model work does not get a service just to host a `do_action` — see
[backend.md](backend.md). `CalendarController` is the current example. The moment that write
moves into a service, the `do_action` moves with it.

**Emit once.** Don't `do_action` *and* directly call the same effect; listeners fire twice.
Watch for the same trap with model lifecycle events — if the model already fires
`creating`/`deleted`, a manual `do_action` for the same conceptual moment is a duplicate.
Pick one path.

---

## Filter the API response

fcom's most transferable pattern. Every list endpoint hands its payload through a filter
before returning:

```php
$data = ['spaces' => $spaces];

return apply_filters('fluent_community/spaces_api_response', $data, $this->request->all());
```

Real ones: `spaces_api_response`, `space_members_api_response`,
`reactions_api_response`.

This is what lets an add-on enrich a response without forking the controller — the pro
plugin, the fcom bridge, or a customer's snippet appends a field. Cheap to add now,
impossible to retrofit once third parties depend on the exact shape.

Name it `fluent_event/<noun>_api_response`, pass the request as the second argument.

---

## When to add a filter

Add one where an integrator would otherwise have to fork you:

- **Permission decisions** — `can_manage_calendar`, `can_view_guest_list`. fcom filters
  every one of these.
- **API response payloads** — as above.
- **Rendered lists** — menu items, default settings, available options.
- **Defaults** — `default_event_cover`, upload constraints.

Do **not** add one speculatively in the middle of business logic. A filter is a public API:
once shipped, changing its arguments breaks sites silently. Fewer, well-placed filters beat
many shallow ones.

---

## Documenting them

Every hook gets a docblock naming its arguments and when it fires. These are the plugin's
public API, and they are the part nobody can read from the code without grepping the whole
tree.
