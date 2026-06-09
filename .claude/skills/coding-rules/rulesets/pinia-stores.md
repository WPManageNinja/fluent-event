# pinia-stores.md — Pinia store discipline

Triggered when editing `src/stores/**`, adding a new `defineStore`, or changing `pinia-plugin-persistedstate` config.

## What's actually in the codebase

7 Pinia stores in `src/stores/`, exported via `src/stores/index.js`:

| Store | Purpose | TTL | Persistence |
|---|---|---|---|
| `cache.js` | App-wide cache with TTL + LRU + pattern invalidation | per-entry (5min default) | No |
| `feed.js` | Feeds with per-context lists (global/space/user), optimistic updates, sticky posts, ticker coordination, max 100 feeds/context | 5min | Yes (persistedstate, selective) |
| `space.js` | Space data, membership, topics, current-space tracking | 15min | Yes |
| `user.js` | User profiles, follow/unfollow optimistic, current user | 5min | Yes |
| `notification.js` | Notifications, unread counts, mark-as-read variants, auto-polling | n/a (server-driven) | Yes |
| `ticker.js` | Single global poller distributing to Feed/Notification/Space/User, visibility-aware, exponential backoff | n/a (polling cadence) | No |
| `ui.js` | Scroll positions, feed layout style (list/card/timeline), sort prefs, modal state | n/a | localStorage (prefs) + sessionStorage (scroll) |

Persistence plugin: `pinia-plugin-persistedstate@^3.2.1` (in `package.json`), installed at `src/app.js:116`.

## Store-side API access

Per CLAUDE.md rule 12: **Pinia stores access REST via `this.$api`, NOT `this.$get`/`this.$post`**. The `$api` plugin is registered in `src/app.js:120-128`:

```js
// Inside a store action:
async fetchSomething(id) {
    const data = await this.$api.get(`/items/${id}`);
    this.items[id] = data;
}
```

Components use `this.$get` / `this.$post` mixin (global). Don't mix patterns.

## TTL pattern (cache.js)

Reference: `src/stores/cache.js:97-145`.

```js
state.cache[key] = { data, timestamp, lastAccessed, hits, ttl };

// On get(key):
const age = Date.now() - entry.timestamp;
if (age > (entry.ttl || ttl || 5 * 60 * 1000)) {
    delete state.cache[key];
    return null;
}
entry.lastAccessed = Date.now();
entry.hits++;
return entry.data;
```

When adding a new TTL store (e.g., `coursesStore.js`), reuse `cacheStore.set(key, data, ttl)` / `.get(key)` — don't reimplement TTL logic in every store. The cache store is the centralized layer.

## LRU eviction

`cache.js:134-135, :188-199`: triggers at `maxSize = 500`, prunes 50 oldest by `lastAccessed`. Single source of truth — new caches shouldn't reimplement eviction.

## Pattern-based invalidation

`cache.js:168-181`: substring match. Sample: `cacheStore.invalidatePattern('feeds:space-foo:')` clears all feed lists for that space.

When mutations happen (e.g., creating a feed), the corresponding store's action must invalidate cache keys it would have populated. Pattern in `feed.js`:

```js
async createFeed(feedData) {
    const feed = await this.$api.post('/feeds', feedData);
    this.feeds[feed.id] = feed;
    // Invalidate list caches that no longer reflect the new feed:
    cacheStore.invalidatePattern(`feeds:${this.currentContext}:`);
    return feed;
}
```

## Persistence rules (persistedstate)

- `ui.js` persists feed layout + sort prefs to `localStorage` (long-lived), scroll positions to `sessionStorage` (per-tab).
- `feed.js` / `space.js` / `user.js` / `notification.js` use selective persistence — only metadata, NOT full payloads. Persisting 100 feeds × 1KB each fills localStorage quickly.

When adding persistence to a new store:
- Choose `localStorage` (cross-tab, persistent) or `sessionStorage` (per-tab) explicitly.
- Use `paths: ['fieldA', 'fieldB']` config to limit what's persisted — don't blindly persist `state`.
- NO PII in persisted state. NO tokens. NO secrets. See existing localStorage keys in `always.md`.

## Ticker coordination (ticker.js)

Single global poller — DON'T add a parallel poll loop in another store. The ticker:
- Polls a single `/feeds/ticker` endpoint every 30s (configurable)
- Distributes updates to Feed / Notification / Space / User stores via `context` (`'global'` / `'space-{slug}'` / `'user-{id}'`)
- Pauses on `document.visibilitychange` when tab hidden
- Exponential backoff on errors

New polling need → emit an event from ticker to a target store action, don't create a second poller.

## Store boundaries

- **One store per domain concept**: feed, space, user, notification, ticker, ui, cache. Don't introduce a store that overlaps existing scope (e.g., don't add `commentsStore` — comments live in feeds).
- **Cross-store calls** are allowed via the store imports (`useFeedStore()` from inside `notificationStore`) — but acknowledge the dependency direction; circular reads cause bugs.
- **Components don't poke store state** directly except via getters/actions. No `feedStore.feeds.push(...)` from inside a component.

## Optimistic updates

`feed.js` / `user.js` (follow/unfollow) — pattern:

```js
async toggleFollow(userId) {
    const wasFollowing = this.isFollowing(userId);
    this.setFollowing(userId, !wasFollowing); // optimistic
    try {
        await this.$api.post(`/profile/${userId}/${wasFollowing ? 'unfollow' : 'follow'}`);
    } catch (err) {
        this.setFollowing(userId, wasFollowing); // revert
        throw err;
    }
}
```

Match this shape. Always revert on error; never leave the UI in an inconsistent state.

## REST nonce renewal

When the user's REST nonce expires, `Bits/Rest.js` fires `fcom_renew_rest_nonce` CustomEvent. The renewal flow updates `window.fluentComAdmin.rest.nonce`. Stores don't need to handle this directly — `$api` plugin reads the latest nonce on each call.

## Stores + tests

`src/__tests__/` uses Vitest. New store actions/getters should have at least one test covering the happy path. See `tests.md`.

## Self-audit

For each Pinia store change:
1. Store uses `this.$api`, not `this.$get`/`this.$post`?
2. New cache layer uses `cacheStore.set/get`, not inline TTL?
3. Mutations invalidate matching cache patterns?
4. Persistence: explicit `paths` config (not full-state persist), no PII / no tokens?
5. New polling — uses the existing `ticker.js`, not a parallel loop?
6. Store boundaries respected (one domain per store)?
7. Optimistic updates revert on error?
8. At least one Vitest test for new actions?
