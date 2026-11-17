# WordPress.org Submission: Blocker Findings POV

**Purpose:** decide which audit findings block the WordPress.org submission, and track their fixes.
**Date:** 2026-11-17
**Revision:** `develop` at `b781dd7`; fix status updated at `cd20137`
**Method:** I read every claim again in the source on `develop`, including the vendored framework calls it depends on: `RateLimiter`, `Request::__call` and `Route`. L-1 was proven before and after its fix with an anonymous GET to the local development site. Nothing else ran. No code changed.

## Summary

| ID | Status | My rating | Report | Verdict |
|---|---|---|---|---|
| ✅ L-1 | Fixed in #414 | — | Low | Fixed. No action. |
| 🔴 L-2 | Confirmed | **Low** | Low | Real. Fix with L-4. |
| ⚠️ L-3 | Fixed in #415 | — | Low | Fixed, with a known gap in the guest count. |
| 🔴 L-4 | Confirmed | **Low** | Low | Real. Small fix. |
| ⏸️ L-5 | Partly true | **Info** | Low | Needs a site setting. Optional hardening. |
| ✅ L-6 | Fixed in #419 | — | Low | Fixed. No action. |
| ✅ L-7 | Fixed in #416 | — | Low | Fixed. No action. |
| ✅ L-8 | Fixed in #417 | — | Low | Fixed. No action. |
| ⏸️ L-9 | Confirmed | **Low** | Low | Kept by decision: the `fe_` prefix stays. |
| ✅ L-10 | Fixed in #418 | — | Low | Fixed. No action. |
| ⏸️ L-11 | Paused | **Info** | Low | Likely false positive. Not a blocker for WordPress.org submission. |

**Fix status** on `develop` at `cd20137` (2026-11-17): ✅ fixed, ⚠️ partly fixed or fixed with known gaps, ⏸️ deferred, 🔴 open.

No finding is urgent. L-1 was the only one that broke a page, and #414 fixed it. L-2, L-3 and L-4 can be done anonymously, but none of them reads private data or changes anything a visitor could not already change.

**Submission blockers:** L-6, L-7, L-8 and L-10 are fixed. L-9 is kept by decision, with a reply ready if a reviewer asks. No open finding blocks the WordPress.org submission.

Open, not blockers: L-2 and L-4 (the public registration path). L-5 is optional, and L-11 is paused.

---

## ✅ L-1. A calendar link with an array value breaks the events page

**Concern (was):** Anyone could open the events list with a link like `?fe_calendar[]=x`. The page showed "There has been a critical error", and WordPress emailed the site admin a recovery link.

**Fix (merged):** The plugin now reads `fe_calendar` only when the value is text. Any other value lists all calendars. The fix is in the free plugin, #414.

### Verification

- **Confirmed (before).** `sanitize_title()` received an array and threw a `TypeError` on PHP 8. The stack trace ended at `FrontEndHandler.php(424): sanitize_title(Array)`.
- **Fixed.** `calendarFromUrl()` (`FrontEndHandler.php:381-387`) returns `''` for any value that is not a string. Both readers use it (`:375`, `:432`).
- **Proven.** On the local site, `/event/?fe_calendar%5B%5D=x` returned 500 before the fix and returns 200 after it. With all six list parameters sent as arrays, the page still returns 200.

**Verdict:** fixed. No action.

---

## 🔴 L-2. The registration rate limit counts per event, not per visitor

**Concern:** The limit on the public registration form is meant to stop one visitor from sending many registrations. A visitor can get past it: each event has its own limit, every IPv6 address counts separately, and requests sent at the same moment are all let through.

**Fix:** Count per visitor for the whole form, not per event. Treat an IPv6 /64 as one visitor. The change is in the free plugin's `Throttle` middleware.

### Verification

- **Confirmed.** `Throttle::makeTransientKey` uses `$request->getRoute()` (`app/Http/Middleware/Throttle.php:27`). `Request::__call` passes this to `WP_REST_Request::get_route()` (vendor `Http/Request/Request.php:845-858`), which returns the actual path with the event ID in it. Each event therefore gets its own counter.
- **Confirmed.** A visitor is keyed by `$request->getIp()` (`:25`). An IPv6 address is used in full, so a /64 gives an attacker any number of keys.
- **Confirmed.** The vendor `RateLimiter::handle` (`:54-75`) reads the count with `get_transient`, adds one, and writes it back with `set_transient`. Two requests that arrive together both read the same count.
- **Refuted, part.** The IP cannot be spoofed. It comes from `REMOTE_ADDR`, and `config/trustedproxy.php` trusts no proxy by default.
- **Limits:** `register` allows 10 per minute and both `register-guest` routes allow 5 per minute (`app/Http/Routes/api.php:73-81`).

### Rating

**Low.**

- **Attacker needs:** nothing. These are public routes.
- **Result:** more registrations and confirmation emails than the limit intends (see L-4). No data access.
- **Side effect:** behind a CDN that is not set up as a trusted proxy, every visitor shares one IP. One visitor can then block registration for that event for a minute.

### Fix

```diff
-    public function __construct($perMinute)
+    public function __construct($perMinute, $action)
     {
         parent::__construct(...);
+        $this->action = $action;
     }

     protected function makeTransientKey($request)
     {
-        $who = is_user_logged_in() ? 'user:' . get_current_user_id() : $request->getIp();
+        $who = is_user_logged_in() ? 'user:' . get_current_user_id() : $this->visitorKey($request->getIp());

-        return App::config()->get('app.slug') . '_rate_limit_' . md5($who . '|' . ($request->getRoute() ?: 'unknown'));
+        return App::config()->get('app.slug') . '_rate_limit_' . md5($who . '|' . $this->action);
     }
```

- `visitorKey()` masks an IPv6 address to its /64 and returns an IPv4 address as it is.
- In `api.php`, name each limit, for example `new Throttle(5, 'register_guest')`.
- **Atomic count (optional):** use `wp_cache_add` and `wp_cache_incr` when a persistent object cache is present. Without one, the transient stays, and the race stays narrow.
- **Behavior change:** the limit then covers all events together. Five guest registrations per minute is still more than a real visitor needs.

**Verdict:** real, Low. Fix with L-4. Both are on the public registration path, where L-3 is already fixed.

---

## ⚠️ L-3. The public registration form shows whether an email is already registered

**Concern (was):** A stranger could find out whether a person registered for a public event. They type the person's email into the form, and the error "A guest with this email is already on this event." confirms it.

**Fix (merged, #415):** On the two public paths, a repeated email gets the same success message as a new registration, and nothing is saved. The form is validated before the repeated-email check, so an invalid name gets the same error either way. The admin "Add guest" form keeps the clear error.

**Known gap:** when the event shows its guest count, the count does not go up after a repeated email. The PR lists this under Known limitations.

### Verification

- **Confirmed.** The `email` rule is `unique:fe_guests,email,NULL,id,event_id,{id}` (`app/Http/Requests/GuestRequest.php:40`), with the message at `:64`.
- **Confirmed.** `PortalEventController::registerGuest` runs this rule on anonymous input (`:41-46`). `CommunityEventController::registerGuest` does the same for logged-out visitors (`:235-239`).
- **Partly limited.** If the email is not registered, the call registers it and sends that address an email. A probe for an unregistered email therefore leaves a trace.
- **Confirmed.** The limits in L-2 slow this down but do not stop it.

### Rating

**Low.**

- **Attacker needs:** the event page, which gives the nonce, and a guess at an email.
- **Result:** they learn whether that one email is on that one event. This matters for sensitive events.

### Fix

```diff
         $data = GuestRequest::sanitize(Arr::only($request->all(), ['name', 'email']));
+
+        // The same answer for a repeated email, so the form does not show who registered.
+        if (Guest::query()->where('event_id', $event->id)->where('email', $data['email'])->exists()) {
+            return $this->registeredResponse($event);
+        }
+
         $this->validate(
```

- `registeredResponse()` returns the same message and status as a new registration, with no meeting link. A fresh registration returns the link only when the event is online and the guest has a seat.
- Apply the same change in `CommunityEventController::registerGuest`.
- **Unchanged:** the admin `createGuest` path keeps the unique error.

**Verdict:** fixed in #415, with the guest-count gap above.

---

## 🔴 L-4. Anyone can make the site send its confirmation email to any address, with their own text in it

**Concern:** A stranger can type someone else's email and a misleading "name" into the registration form. The site then sends its own branded confirmation to that person, and the email opens with the stranger's text. This can serve as phishing.

**Fix:** Do not greet visitor registrations with the name they typed, or cut the name short. The change is in the free plugin's email builder.

### Verification

- **Confirmed.** `registerVisitor` stores the typed `name` and `email`. `queueEmail` then sends the confirmation (`PortalEventController.php:48-52`, `CommunityEventController.php:241-245`).
- **Confirmed.** `EventReminderService::buildGreeting` (`:340-351`) opens with `Hi %s,` and the guest's name. The name is HTML-escaped, so there is no injection, but the text is what the visitor wrote.
- **Confirmed.** `name` can be up to 255 characters (`GuestRequest.php`). A mail client turns a bare domain in that text into a link.
- **Limited.** `UNIQUE (event_id, email)` allows one email per address per event. L-2 multiplies the volume across events.

### Rating

**Low.** No data leaks. The risk is to the site's sending reputation and to the recipient.

### Fix

```diff
     protected static function buildGreeting($event, $guest, $intro)
     {
+        // A visitor's typed name is not verified, so their emails greet without it.
+        $name = $guest->user_id ? $guest->name : '';
+
         return sprintf(
             esc_html__('Hi %s,', 'fluent-event'),
-            esc_html($guest->name ?: __('there', 'fluent-event'))
+            esc_html($name ?: __('there', 'fluent-event'))
         ) . ...
```

- **Behavior change:** visitors get "Hi there," instead of their name. Members, whose name comes from their account, keep the greeting.
- **Alternative:** keep the name but cut it to about 50 characters. This only shortens the abuse; it does not remove it.
- **Larger option:** double opt-in for visitor registrations, where the guest confirms by email before the seat counts. That is a product decision.

**Verdict:** real, Low. Small fix.

---

## ⏸️ L-5. A member can take over a guest registration made with their account email

**Concern (as reported):** A member whose account email matches a guest row can claim that row and cancel it.

**Fix:** Nothing urgent. Both WordPress and FluentCommunity ask the user to confirm an email change before it applies, so a member cannot simply switch to someone else's address. An optional guard limits the claim to rows an admin added.

### Verification

- **Confirmed.** `registerMember` finds a guest row by `user_id`, then by `email` (`app/Services/RegistrationService.php:43-48`). It writes the member's `user_id` onto an unowned row (`:77-91`).
- **Refuted, main premise.** An email change needs confirmation.
  - In FluentCommunity, a member changing their own email gets a confirmation mail, and the email does not change until they confirm (`fluent-community/app/Http/Controllers/ProfileController.php:453-461`).
  - WordPress core does the same on the profile screen.
- **Remaining path.** A site that lets people sign up with any email and no verification. The attacker signs up with the victim's address, which must not already have an account.

### Rating

**Info, not Low.** It needs a site setting that skips email verification at signup, and an email that has no account yet.

### Fix (optional hardening)

```diff
-        $guest = Guest::query()->where('event_id', $event->id)->where('user_id', $user->ID)->first()
-            ?: Guest::query()->where('event_id', $event->id)->where('email', $user->user_email)->first();
+        $guest = Guest::query()->where('event_id', $event->id)->where('user_id', $user->ID)->first()
+            ?: Guest::query()->where('event_id', $event->id)->where('email', $user->user_email)
+                ->where('source', 'manual')->first();
```

- **Behavior change:** a visitor registration made with the same email is no longer linked to the member. The member gets a new row, which hits `UNIQUE (event_id, email)`. Handle that by refusing with a clear message. Do not use the optional fix as it stands.

**Verdict:** mostly not real today. Revisit only if a no-verification signup becomes common.

---

## ✅ L-6. A hard-coded token is in a comment, on a token login that does nothing

**Concern (was):** A WordPress.org reviewer who sees what looks like a credential in the code will ask for it to be removed, even in a comment. The token login it belongs to is unused scaffold.

**Fix (merged, #419):** The comment, the token login code and the unused `Can` middleware (I-2) are removed. Admin access stays `manage_options`.

### Verification

- **Confirmed.** `app/Utils/Auth/Authorizer.php:80`: `// Plain $token: p0K2t2…`.
- **Confirmed, no access.** `getUserToken()` (`:94-103`) has only comments and returns `null`. `Hash::check()` is `password_verify($token, null)` (vendor `Support/Hash.php:57`), which is `false`. The default policy therefore fails closed.
- **Side effect.** A request to an admin route that sends `X-FLUENT-APP-TOKEN` passes `null` to `password_verify()`. That is a deprecation notice on PHP 8.1 and later.
- **Related.** `app/Http/Middleware/Can.php:41-46` is also unused scaffold. Its `has()` returns `true`. It belongs in the same clean-up (audit I-2).

### Rating

**Low.** It cannot be exploited. The risk is a review round.

### Fix

```diff
     public function check(Request $request, $capability, ...$args)
     {
         try {
-            if ($this->authorize($request, $capability, ...$args)) {
-                return true;
-            }
-
-            return $this->authorizeUsingToken($request, ...$args);
-
+            return $this->authorize($request, $capability, ...$args);
         } catch (Exception $e) {
             return false;
         }
     }
```

- Delete `authorizeUsingToken()`, `getUserToken()`, `$appIdKey`, `$tokenKey` and the `Hash` import.
- Delete `app/Http/Middleware/Can.php`. Nothing references it.

**Verdict:** fixed in #419. No action.

---

## ✅ L-7. Inline `<script>` and `<style>` are printed directly

**Concern (was):** WordPress.org reviewers ask plugins to print scripts and styles through the WordPress functions for them. The plugin prints one `<script>` on the events list and one `<style>` on every admin page directly.

**Fix (merged, #416):** The script is printed with `wp_get_inline_script_tag()` and the style with `wp_add_inline_style()`. Verified in the browser before and after.

### Verification

- **Confirmed.** `FrontEndHandler::rememberChoices` builds a raw `<script>` with `ob_start()` (`FrontEndHandler.php:787-818`). Its data is passed through `wp_json_encode`, so it is safe.
- **Confirmed.** `app/Hooks/logoicon.php:20-23` echoes a `<style>` on `admin_head` for every admin screen.
- **Confirmed, dev only.** `logoicon.php:13-14` echoes two `<link rel="icon">` tags only when `env` starts with `dev`. The release build sets `env` to `production`.

### Rating

**Low.** It cannot be exploited. The risk is a review round.

### Fix

```diff
-        <script>
             (function (choices) { ... })(<?php echo wp_json_encode($choices); ?>);
-        </script>
         <?php
-        return (string) ob_get_clean();
+        return wp_get_inline_script_tag((string) ob_get_clean());
```

```diff
-$app->addAction('admin_head', function() use ($app) {
+$app->addAction('admin_enqueue_scripts', function() use ($app) {
     $slug = sanitize_key($app->config->get('app.slug'));
-    echo '<style>li.toplevel_page_' . esc_html($slug) . ' .wp-menu-image img { width: 20px; height: 20px; }</style>';
+    wp_register_style('fluent-event-menu-icon', false, [], FLUENT_EVENT_PLUGIN_VERSION);
+    wp_enqueue_style('fluent-event-menu-icon');
+    wp_add_inline_style('fluent-event-menu-icon', 'li.toplevel_page_' . $slug . ' .wp-menu-image img { width: 20px; height: 20px; }');
 });
```

- `wp_get_inline_script_tag()` returns a string, so the shortcode output still works after the page head has printed.
- **Check after the change:** the menu icon size in wp-admin, and that a saved view or filter is remembered on the events list.

**Verdict:** fixed in #416. No action.

---

## ✅ L-8. The admin URL filter has a name that other plugins share

**Concern (was):** Hook names must be unique to the plugin. `fluent_connector_base_url` is also used by other plugins built on the same framework. A filter added for one of them would also change FluentEvent's admin links.

**Fix (merged, #417):** The filter is kept and renamed to `fluent_event/admin_base_url`.

### Verification

- **Confirmed.** `AdminMenuHandler.php:52-55` calls `applyFilters('fluent_connector_base_url', …)`, which is a plain `apply_filters` with no plugin prefix (vendor `FoundationTrait.php:235`).
- **Confirmed.** The same name is used by fluent-cart, ninja-tables and fluent-invoice in this install.
- **Confirmed.** No code in FluentEvent adds this filter.

### Rating

**Low.** It cannot be exploited. The risk is a review round and a cross-plugin side effect.

### Fix

```diff
-        $this->baseUrl = $this->app->applyFilters(
-            'fluent_connector_base_url',
-            admin_url('admin.php?page=' . $this->slug . '#/')
-        );
+        $this->baseUrl = admin_url('admin.php?page=' . $this->slug . '#/');
```

**Verdict:** fixed in #417. No action.

---

## ⏸️ L-9. The `fe_` prefix is short

**Concern:** Reviewers often ask for a prefix of four or more characters. The plugin uses `fe_` for the post type `fe_event`, the options `fe_settings` and `fe_version`, and the URL parameters `fe_page`, `fe_calendar` and `fe_search`.

**Fix:** Kept by decision. Answer the reviewer if they ask.

### Verification

- **Confirmed.** `EventPost.php:26`, `Settings.php:13`, `ActivationHandler.php:64,70,104`, `FrontEndHandler.php:27-29`.
- **Context.** The tables are `wp_fe_*`, and sites on self-hosted 1.0.0–1.0.5 already store data under these names. A rename would need a migration for the post type, the options and the stored post rows.

### Rating

**Low.** It is a naming concern only.

### Suggested reply if a reviewer asks

> "`fe_` is used for the post type, two options and three URL parameters. The plugin is already in use under these names, so renaming them would need a data migration. All functions, classes, constants and hooks use the longer `fluent_event` / `FluentEvent` prefix."

**Verdict:** real, Low. Kept by decision. No change.

---

## ✅ L-10. A stylesheet handle is generic and has no version

**Concern (was):** Reviewers ask for prefixed script and style handles. One admin stylesheet gets the handle `app.js_0_css` and no version, so a browser can also keep an old copy after an update.

**Fix (merged, #418):** The handles are now `fluent-event-<file>-<n>` and load with the plugin version.

### Verification

- **Confirmed.** `app/Utils/Enqueuer/Vite.php:204-205` enqueues `$file['file'] . '_' . $key . '_css'` with version `null`.
- **Confirmed.** With today's manifest, the handle for the admin app is `app.js_0_css`.
- **Confirmed.** Every other handle in the plugin is prefixed and uses `FLUENT_EVENT_PLUGIN_VERSION`.

### Rating

**Low.** It cannot be exploited.

### Fix

```diff
                 wp_enqueue_style(
-                    $file['file'] . '_' . $key . '_css', static::styleUrl($assetPath . $path), [], null
+                    'fluent-event-' . sanitize_key($file['file'] . '-' . $key), static::styleUrl($assetPath . $path), [], FLUENT_EVENT_PLUGIN_VERSION
                 );
```

- **Check after the change:** the admin app's styles load on every FluentEvent screen.

**Verdict:** fixed in #418. No action.

---

## ⏸️ L-11. SEO plugin sitemaps can list unlisted events

**Concern:** An unlisted event should open only by its link. Yoast, Rank Math and AIOSEO build their own sitemaps, and they can list unlisted event links. That makes the events findable.

**Fix:** Take hidden events out of each SEO plugin's sitemap through its exclusion filter. The change is a small integration in the free plugin.

### Verification

- **Confirmed.** An unlisted event is stored as `post_status = publish` (`app/Models/Event.php:23`). The post type is public (`app/CPT/EventPost.php:41-43`).
- **Confirmed, safe in core.** The plugin limits core archives, feeds and core sitemaps through `pre_get_posts` (`FrontEndHandler::limitListings`, `:143-168`), and marks unlisted events `noindex`.
- **Not verified.** That each SEO plugin reads posts with its own SQL and skips `pre_get_posts`. This comes from how those plugins usually work. I did not read their code.
- **Partly true.** Events in a private or draft calendar still return 404 when opened. Only their link and title can show in the sitemap.

### Rating

**Low.** It needs an SEO plugin with sitemaps turned on. Confidence: medium.

### Fix

```php
// Each filter receives post IDs to leave out of the sitemap.
add_filter('wpseo_exclude_from_sitemap_by_post_ids', [$this, 'hiddenEventPostIds']);
add_filter('rank_math/sitemap/exclude_posts', [$this, 'hiddenEventPostIdsCsv']);
add_filter('aioseo_sitemap_exclude_posts', [$this, 'hiddenEventPostIds'], 10, 2);
```

- `hiddenEventPostIds()` returns the post IDs of events that are not public in a public calendar, the reverse of `eventPostIds(['public'])`.
- **Before you ship:** confirm each filter's name and argument format against the current version of each plugin.
- **Root fix (larger):** store unlisted events under their own post status. That changes how single pages are resolved, so plan it separately.

**Verdict:** paused as a likely false positive. The claim rests on unverified assumptions about third-party SEO plugins, and core sitemaps, archives and feeds are already limited and unlisted events are `noindex`. Not a blocker for WordPress.org submission. Revisit only if a sitemap from Yoast, Rank Math or AIOSEO is shown to list an unlisted event.

---

## Info findings

| ID | Status | File | Verdict |
|---|---|---|---|
| ✅ I-1 | Fixed in #420 | `app/Http/Requests/SettingsRequest.php` | The email notification settings are now rebuilt from their known keys. Unknown keys are dropped. |
| ✅ I-2 | Fixed in #419 | `app/Http/Middleware/Can.php` (deleted) | `Can.php` is deleted, with L-6. |
| 🔴 I-3 | Confirmed | `app/Hooks/actions.php:24`, `app/Hooks/Handlers/ExceptionHandler.php` | `ExceptionHandler` listens to a hook nothing fires. Delete the registration and the class. |
| ⏸️ I-4 | Confirmed | `boot/app.php:15,24,34,63` | `fluent_exception` is shared with the framework. Optional rename to `fluent_event/exception`. |
| ⏸️ I-5 | Confirmed | `config/app.php:13`, `app/Utils/Enqueuer/Vite.php:289-317` | The repo keeps `'env' => 'dev'`; `build.sh` sets `production` and fails if it cannot. Ship only through `build.sh`. |
| ⏸️ I-6 | Confirmed | `app/Views/portal/event-side.php:181-186`, `:248-252` | The "Add to calendar" links use `esc_attr`, which the code comment explains. Keep it and add a `phpcs:ignore` note. |
| ⏸️ I-7 | Confirmed | `app/Views/**`, `config/app.php:1` | The ABSPATH guard uses an unusual form with a CLI exception. It is safe. Optional change to `defined('ABSPATH') \|\| exit;`. |
| ✅ I-8 | Fixed in #421 | `app/Models/Post.php`, `app/Models/User.php` | Both models now declare `$fillable = []` and `$guarded = ['*']`. An empty `$fillable` alone did not block anything, because the base model guards only `id` and `ID`. |
| ⏸️ I-9 | Confirmed | `Modules/FluentCommunity/FeedHandler.php:228-236` | A post moved to another space keeps the old `is_public` on its activity rows. FluentCommunity ignores the flag today. Fix if needed: set `is_public` from the target space. |
| ⏸️ I-10 | Confirmed | `app/Hooks/Handlers/FrontEndHandler.php:168`, `:448-450` | `limitListings` replaces another query's `post__in`, and the list URL uses the queried object on archive pages. Neither shows more than public events. Fix if needed: intersect `post__in`, and use the queried object only when `is_singular()`. |
