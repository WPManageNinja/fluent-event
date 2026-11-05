import { describe, it, expect, vi, afterEach } from 'vitest';
import middlewares from '@/router/middlewares';
import { runBeforeMiddleware, runRouteMiddleware, runAfterMiddleware } from '@/router/middlewareHandler';
import createAdminRouter from '@/router';

// The router lazy-loads every page it lands on. Stubbed so the router tests never compile real pages,
// which on a loaded run could outlast the 5s test timeout; only where navigation ends up is asserted.
const page = vi.hoisted(() => () => ({ default: { render: () => null } }));

vi.mock('@/modules/dashboard/index.vue', page);
vi.mock('@/modules/calendars/index.vue', page);
vi.mock('@/modules/calendars/CreateOrUpdateCalendar.vue', page);
vi.mock('@/modules/calendars/detail.vue', page);
vi.mock('@/modules/events/index.vue', page);
vi.mock('@/modules/events/CreateOrUpdateEvent.vue', page);
vi.mock('@/modules/events/detail.vue', page);
vi.mock('@/modules/settings/index.vue', page);
vi.mock('@/modules/settings/general.vue', page);
vi.mock('@/modules/settings/email-notifications.vue', page);
vi.mock('@/components/NotFound', page);

const app = { config: { globalProperties: { appVars: { me: { id: 7 } } } } };
const to = (meta) => ({ meta });
const from = to({});

describe('middleware handler', () => {
    afterEach(() => {
        middlewares.global.before = {};
        middlewares.global.after = {};
        middlewares.route = {};
    });

    it('runs the middleware a route names', async () => {
        middlewares.route.guard = async () => ({ path: '/elsewhere' });

        expect(await runRouteMiddleware(to({ middleware: 'guard' }), from, app)).toEqual({ path: '/elsewhere' });
    });

    it('accepts a list of middleware names', async () => {
        middlewares.route.guard = async () => ({ path: '/elsewhere' });

        expect(await runRouteMiddleware(to({ middleware: ['guard'] }), from, app)).toEqual({ path: '/elsewhere' });
    });

    it('passes a route with no middleware or an unknown name', async () => {
        expect(await runRouteMiddleware(to({}), from, app)).toBe(true);
        expect(await runRouteMiddleware(to({ middleware: 'nope' }), from, app)).toBe(true);
    });

    it('stops at the first middleware that redirects', async () => {
        const later = vi.fn();
        middlewares.global.before = {
            pass: vi.fn(),
            redirect: vi.fn(async () => '/login'),
            later
        };

        expect(await runBeforeMiddleware(to({}), from, app)).toBe('/login');
        expect(later).not.toHaveBeenCalled();
    });

    it('cancels navigation when a middleware returns false', async () => {
        middlewares.global.before = { cancel: () => false };

        expect(await runBeforeMiddleware(to({}), from, app)).toBe(false);
    });

    it('skips entries that are not functions', async () => {
        middlewares.global.before = { broken: 'not-a-function', pass: () => true };

        expect(await runBeforeMiddleware(to({}), from, app)).toBe(true);
    });

    it('runs every after middleware', async () => {
        const first = vi.fn();
        const second = vi.fn();
        middlewares.global.after = { first, second };

        await runAfterMiddleware(to({}), from, app);

        expect(first).toHaveBeenCalled();
        expect(second).toHaveBeenCalled();
    });
});

describe('admin router', () => {
    // Only site administrators reach the admin app, so no page is gated in the browser.
    it('opens every page without a permission check', async () => {
        const router = createAdminRouter(app);

        await router.push('/events/12/edit');
        expect(router.currentRoute.value.name).toBe('event_edit');

        await router.push('/settings');
        expect(router.currentRoute.value.name).toBe('settings_general');
    });
});
