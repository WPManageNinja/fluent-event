# tests.md — test discipline (Vitest / PHPUnit / Playwright)

Triggered when editing `tests/**`, `src/__tests__/**`, `phpunit.xml.dist`, `playwright.config.js`, `vitest.config.js`, or deleting a test file in this diff. Also fires when a new feature is added without test coverage in a directory that already has tests.

## What's in the codebase

- **Vitest** — JS / Vue unit tests. Location: `src/__tests__/`. Currently 7 active `*.spec.js` files (BookmarkIcon.spec.js + 6 icon specs under `src/__tests__/components/Icons/`: NotificationIcon, ImageIcon, VideoIcon, PlayIcon, HeartIcon, ChatIcon) + 2 disabled `.spec.js.skip` files (UserBadge, VerifiedSign). Config: `vitest.config.js`.
- **PHPUnit** — PHP backend tests. Location: `tests/`. Config: `phpunit.xml.dist`.
- **Playwright** — E2E tests. Config: `playwright.config.js`. Tests in `tests/` (or per-feature subdir).

Disabled tests use `.spec.js.skip` extension (e.g., `UserBadge.spec.js.skip`, `VerifiedSign.spec.js.skip`). Don't enable them without verifying they still apply.

## Test discipline

1. **Don't delete tests** unless you've verified the tested behavior is also removed. Deleting a test for code that's still live = silent regression risk.
2. **New code that has a sibling test surface gets a test.** If you add a Vue component in `src/Modules/X/`, check if `src/__tests__/Modules/X/` has tests for that module — extend them. Don't ship code with strictly-less coverage than its neighbors.
3. **New Pinia store actions** — add a Vitest test for at least the happy path. See `pinia-stores.md`.
4. **New REST endpoint** — if `tests/` has PHPUnit tests for similar endpoints, add one. Focus: validation rejection, auth rejection, happy path, ownership check.
5. **New policy method** — PHPUnit test covering: rejected unauthenticated, rejected unauthorized, allowed authorized.

## Vitest patterns

Reference: `src/__tests__/components/BookmarkIcon.spec.js`.

```js
import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import BookmarkIcon from '@/Pieces/Icons/BookmarkIcon.vue';

describe('BookmarkIcon', () => {
    it('renders SVG with active class when bookmarked', () => {
        const wrapper = mount(BookmarkIcon, { props: { active: true } });
        expect(wrapper.find('svg').exists()).toBe(true);
        expect(wrapper.classes()).toContain('active');
    });
});
```

Mock Pinia stores by passing `global.plugins: [createTestingPinia({ initialState: { ... } })]` to `mount()`.

## PHPUnit patterns

Use `tests/` directory pattern. Bootstrap via `phpunit.xml.dist`. Mock WP via WordPress test framework or `Brain\Monkey` (if introduced — verify presence in `composer.json` dev deps).

When adding a backend test:
- Test ONE method per test method.
- Arrange-Act-Assert structure.
- Mock external dependencies (Action Scheduler, `wp_mail`, `wp_remote_*`).
- Don't hit the real DB unless using a proper test fixture.

## Playwright patterns

E2E tests in `tests/` or per-feature subdir. Config: `playwright.config.js`.

When adding a Playwright test:
- Use `data-testid` attributes on Vue components (don't rely on CSS selectors that could change with restyle).
- Test full user flows, not units.
- Clean up state in `afterEach` (delete test users, reset test data).

## When tests are HARDER to write than the feature

- If TDD-style tests would take longer than the feature itself, write the feature + a single smoke test + document why deeper tests are deferred in a TODO with a date.
- DON'T just skip tests silently.

## CI integration

- `.github/workflows/**` should run Vitest + PHPUnit on PR. Verify when editing CI configs (see `deps-build.md`).
- Playwright is heavier — may only run on main / release branches.

## Test deletion as a red flag

The audit's testing agent flags test deletion specifically:
- If you remove a test, the audit will ask why.
- Acceptable reasons: behavior removed, test was always-skip, test was duplicated by another.
- NOT acceptable: "test was failing, removed to make CI green."

## Self-audit

For each change:
1. Did this change touch a directory that has tests? If yes, are tests still passing / extended for new behavior?
2. Did this change delete a test? Is the matching behavior also deleted (or merged into another test)?
3. New Pinia store action / new REST endpoint / new policy method → matching test added (or follow-up TODO with date)?
4. CI workflows still wire `vitest` + `phpunit` runs?
5. Playwright config still valid (if E2E exists for this feature)?
