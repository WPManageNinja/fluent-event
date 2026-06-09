# routes — REST routes + AJAX surfaces

Applies to REST route files and `wp_ajax_*` registrations.

## Two surfaces

1. **Framework REST routes** — declared via
   `$router->prefix(...)->withPolicy(...)->group(...)`, resolved to
   `register_rest_route`. Most new endpoints belong here.
2. **`wp_ajax_*` actions** — for the custom auth flow and admin tooling. Prefix every
   action (`fluent_events_*` / `fevents_*`) to avoid collisions.

## Namespace

- REST namespace `fluent-events/v1` from `config/app.php` (confirm). Don't hardcode the
  literal in route URLs where a framework lookup exists — keeps fork compatibility.

## Policy binding (CRITICAL)

```php
$router->prefix('events')->withPolicy('EventPolicy')->group(function ($router) {
    $router->delete('/{eventSlug}', 'EventController@deleteBySlug')->alphaNumDash('eventSlug');
});
```

The framework matches `EventPolicy::deleteBySlug()`; if absent it falls back to
`EventPolicy::verifyRequest()`.

Rule: when adding a route to a `withPolicy(...)` group, ensure either —
1. the policy has a method matching the controller action (REQUIRED for destructive
   `delete*` / `remove*` / `update*`), or
2. `verifyRequest()` is sufficient (acceptable for GET reads).

Never let a destructive action rely on the `verifyRequest` fallback.

## Param constraints

Use inline validators — `->int('event_id')`, `->alphaNumDash('eventSlug')`,
`->alphaNum('hash')`. Don't accept unconstrained string params; they reach handlers
with arbitrary content.

## Public AJAX / public REST

When registering a `_nopriv_` AJAX action OR a REST route with
`permission_callback => '__return_true'`:

1. Verify a nonce before processing: `check_ajax_referer('<resource_bound_action>', 'nonce')`.
2. Rate-limit (apply to all public registrations).
3. Resource-bound nonces for state-changing actions: `wp_create_nonce("fevents_invite_$inviteHash")`.
4. Don't accept a user-controlled ID as authorization — load the resource by hash and
   verify the action is allowed.
5. Return only public fields; verify ownership inside the handler (avoid IDOR).

## Nonces

- Authenticated REST routes auto-validate `X-WP-Nonce` via the framework — don't
  re-implement.
- Email-link callbacks (reset, invitation, signed login URL): bind the nonce to the
  specific resource — `wp_create_nonce("fevents_accept_invite_$invitationId")`.

## Error response

- `$this->sendError(['message' => ..., 'errors' => ...], 422)` for validation;
  `$this->sendSuccess([...])` on success.

## Self-audit

1. Bound to a `withPolicy(...)` group, with an action-named method (destructive) or a
   sufficient `verifyRequest()` (reads)?
2. Param validators applied?
3. Validation at controller entry via `$this->validate(...)`?
4. Public AJAX/REST → nonce + rate-limit + ownership before any write?
5. Error response uses `sendError(...)`?

**Enforced by** scanner rule 4 (`permission_callback => '__return_true'`).
