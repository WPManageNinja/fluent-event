# policies.md — authorization policies

Triggered when editing `app/Http/Policies/**` (free), pro `app/Http/Policies/**`, `app/Services/Helper.php` (`isSiteAdmin`, `canAccessPortal`), `app/Models/User.php` (role helpers), `Modules/Course/Http/Policies/CourseAdminPolicy.php`, or `Modules/Auth/Classes/InvitationPolicy.php`.

## What's actually in the codebase

**Free** — 5 policy files in `app/Http/Policies/`: `BasePolicy`, `AdminPolicy`, `PortalPolicy`, `SpacePolicy`, `UserPolicy`. Plus `Modules/Course/Http/Policies/CourseAdminPolicy.php` and `Modules/Auth/Classes/InvitationPolicy.php`.

**Pro** — 3 own policy files in `../fluent-community-pro/app/Http/Policies/`: `UserPolicy.php`, `TopicPolicy.php`, `ModerationPolicy.php`. Pro also REUSES free's `AdminPolicy`, `PortalPolicy`, `CourseAdminPolicy` via the framework's `withPolicy(...)` resolution + cross-namespace import. Pro models extend free models heavily (e.g. pro `app/Models/User.php` extends free `User`).

**FluentCommunity is role-based, NOT capability-based.** There is no plugin-specific capability layer (different from FluentBooking). Authorization flows through:

- `Helper::isSiteAdmin()` — WordPress admin check (`app/Services/Helper.php:156`)
- `Helper::canAccessPortal($userId)` — settings-driven access (`Helper.php:424`)
- `XProfile` model — user must have `status = 'active'` profile
- `User::hasCommunityAdminAccess()`, `hasSpaceManageAccess()`, `getSpaceRole($space)`, `verifySpacePermission($permission, $space)` (`User.php:384-636`)
- Role values: `admin`, `moderator`, `member`, `pending`, `course_admin`, `course_creator`
- Permission keys (from `User::getRolePermissions()` L467-498): `super_admin`, `community_admin`, `community_moderator`, `course_creator`, `course_admin`, `delete_any_feed`, `edit_any_feed`, `delete_any_comment`, `edit_any_comment`, `read`

## Policy chain (verified)

```
BasePolicy::verifyRequest()         → Helper::isSiteAdmin()
  └─ AdminPolicy::verifyRequest()   → Helper::isSiteAdmin()
  └─ PortalPolicy::verifyRequest()  → XProfile active + Helper::canAccessPortal($userId)
  └─ SpacePolicy::verifyRequest()   → XProfile active; GET → portal access; non-GET → canManageCommunity
  └─ UserPolicy::verifyRequest()    → Helper::isSiteAdmin() (extends framework Policy directly, NOT BasePolicy)
```

Every existing policy class overrides `verifyRequest`. No silent allow at the base level.

## Policy method resolution (CRITICAL)

Framework auto-suffixes `withPolicy('SpacePolicy')` with the controller's action method name. Resolution at `vendor/wpfluent/framework/src/WPFluent/Foundation/Concerns/FoundationTrait.php::parsePolicyHandler()` (function starts L107; fallback statement at L122):

```php
if (!method_exists($class, $method)) {
    $method = 'verifyRequest';
}
```

**If the policy doesn't define a method matching the controller action, it falls back to `verifyRequest`** — which is generally weaker. Per CLAUDE.md rule 3: "Policy methods must exist for destructive controller methods — otherwise falls back to weaker `verifyRequest()`."

Rule: for any DESTRUCTIVE controller method (`deleteSpace`, `removeMember`, `deleteComment`, etc.), the matching policy class MUST define a same-named method that enforces destructive-specific checks (e.g., `canManageSpace`). Don't rely on `verifyRequest` for destructive flows.

## Existing method overrides (use as reference)

- `PortalPolicy`: `getBookmarks`, `updateLinks`
- `SpacePolicy`: `join`, `leave`, `deleteBySlug`, `deleteById`, `addMember`, `removeMember`, `getSpaceGroups`, `createSpaceGroup`, `updateSpaceGroup`, `deleteSpaceGroup`, `getMetaSettings`
- `UserPolicy`: `create`

## ⚠ Known concern: SpacePolicy slug vs space_id

`SpacePolicy::canManageCommunity` (`SpacePolicy.php:~116`) and `canManageSpace` (~133) resolve the target space via `$request->get('space_id')` — which is a query parameter — NOT the URL-bound `{spaceSlug}` route param. `addMember` / `removeMember` (and similar) bind by slug in the route but check by `space_id` in the policy. An attacker passing `?space_id=<spaceTheyAdmin>` while hitting a destructive route on a different slug could bypass.

When editing these methods, prefer resolving the target from the URL-bound parameter (the route binding), or assert that `$slug-resolved-space.id === $request->get('space_id')` before authorizing.

## Capability + ownership

Capability check alone is not ownership. After confirming the role check, verify the target resource belongs to the caller's scope:

```php
public function deleteFeed(Request $request)
{
    $user = $this->getUser();
    $feedId = (int) $request->get('feed_id');
    $feed = Feed::find($feedId);
    if (!$feed) {
        return false;
    }
    // Owner OR has community-admin OR has space-level edit permission
    return $feed->user_id === $user->ID
        || $user->hasCommunityAdminAccess()
        || $user->verifySpacePermission('delete_any_feed', $feed->space);
}
```

Reuse existing helpers — don't reimplement.

## Never trust client-provided IDs / roles / flags

- `role=admin` from request body → ignored, look up server-side
- `user_id` from request body for "act on behalf of" flows → reject; use `get_current_user_id()`
- `space_id` from request body when a URL-bound `{spaceSlug}` exists → cross-check (see known concern above)

## Never `return true` unconditionally

A policy method that always returns `true` is the same as no policy. Has to be a deliberate decision (truly public action) — and even then prefer adding a method that returns `true` with an explicit comment than letting it fall through.

## Self-audit

For each policy method added/changed in this batch:
1. Policy class overrides `verifyRequest` (no silent base-class fallback)?
2. Action-named method exists OR `verifyRequest` handles the action correctly?
3. Destructive controller method (`delete*`, `remove*`) has a matching action-named policy method?
4. Authorization uses `Helper::isSiteAdmin` / `Helper::canAccessPortal` / `User::*` helpers (not hand-rolled cap checks)?
5. Ownership / scope check after role check?
6. Target resource resolved from URL-bound param (not query body) when both exist?
7. No client-trusted IDs / roles / flags?
8. No unconditional `return true`?
