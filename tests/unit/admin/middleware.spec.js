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
vi.mock('@/modules/settings/team.vue', page);
vi.mock('@/components/Unauthorized', page);
vi.mock('@/components/NotFound', page);

const appFor = (me) => ({ config: { globalProperties: { appVars: { me } } } });
const member = (permissions) => appFor({ id: 7, is_admin: false, permissions });
const to = (meta) => ({ meta });
const from = to({});

describe('permission middleware', () => {
    const permission = middlewares.route.permission;

    it('lets anyone through a route that asks for nothing', async () => {
        expect(await permission(to({}), from, member([]))).toBe(true);
    });

    it('lets a WordPress administrator through without any granted keys', async () => {
        const app = appFor({ is_admin: true, permissions: [] });

        expect(await permission(to({ permissions: ['write_settings'] }), from, app)).toBe(true);
    });

    it('treats manage_all_data as every permission', async () => {
        expect(await permission(to({ permissions: ['write_events'] }), from, member(['manage_all_data']))).toBe(true);
    });

    it('needs only one of the keys when the route matches any', async () => {
        const route = to({ permissions: ['read_events', 'write_events'], match_type: 'any' });

        expect(await permission(route, from, member(['read_events']))).toBe(true);
    });

    it('needs every key when the route does not say otherwise', async () => {
        const route = to({ permissions: ['read_events', 'write_events'] });

        expect(await permission(route, from, member(['read_events']))).toEqual({ path: '/unauthorized' });
        expect(await permission(route, from, member(['read_events', 'write_events']))).toBe(true);
    });

    it('treats a user with no permissions list as holding none', async () => {
        const app = appFor({ id: 7, is_admin: false });

        expect(await permission(to({ permissions: ['read_events'] }), from, app)).toEqual({ path: '/unauthorized' });
    });
});

describe('middleware handler', () => {
    afterEach(() => {
        middlewares.global.before = {};
        middlewares.global.after = {};
    });

    it('runs the middleware a route names', async () => {
        const route = to({ middleware: 'permission', permissions: ['write_events'] });

        expect(await runRouteMiddleware(route, from, member(['read_events']))).toEqual({ path: '/unauthorized' });
    });

    it('accepts a list of middleware names', async () => {
        const route = to({ middleware: ['permission'], permissions: ['write_events'] });

        expect(await runRouteMiddleware(route, from, member([]))).toEqual({ path: '/unauthorized' });
    });

    it('passes a route with no middleware or an unknown name', async () => {
        expect(await runRouteMiddleware(to({}), from, member([]))).toBe(true);
        expect(await runRouteMiddleware(to({ middleware: 'nope' }), from, member([]))).toBe(true);
    });

    it('stops at the first middleware that redirects', async () => {
        const later = vi.fn();
        middlewares.global.before = {
            pass: vi.fn(),
            redirect: vi.fn(async () => '/login'),
            later
        };

        expect(await runBeforeMiddleware(to({}), from, member([]))).toBe('/login');
        expect(later).not.toHaveBeenCalled();
    });

    it('cancels navigation when a middleware returns false', async () => {
        middlewares.global.before = { cancel: () => false };

        expect(await runBeforeMiddleware(to({}), from, member([]))).toBe(false);
    });

    it('skips entries that are not functions', async () => {
        middlewares.global.before = { broken: 'not-a-function', pass: () => true };

        expect(await runBeforeMiddleware(to({}), from, member([]))).toBe(true);
    });

    it('runs every after middleware', async () => {
        const first = vi.fn();
        const second = vi.fn();
        middlewares.global.after = { first, second };

        await runAfterMiddleware(to({}), from, member([]));

        expect(first).toHaveBeenCalled();
        expect(second).toHaveBeenCalled();
    });
});

describe('admin router', () => {
    it('sends a member without manage_all_data from Team settings to the unauthorized page', async () => {
        const router = createAdminRouter(member(['read_settings', 'write_settings']));

        await router.push('/settings/team');

        expect(router.currentRoute.value.name).toBe('unauthorized');
    });

    it('lets a member with any calendar key open the calendars list', async () => {
        const router = createAdminRouter(member(['read_calendars']));

        await router.push('/calendars');

        expect(router.currentRoute.value.name).toBe('calendars');
    });

    it('keeps a read-only member out of the event editor', async () => {
        const router = createAdminRouter(member(['read_events']));

        await router.push('/events/12/edit');

        expect(router.currentRoute.value.name).toBe('unauthorized');
    });
});
