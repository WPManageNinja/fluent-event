import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import Storage from '@/utils/Storage';
import { t } from '../../../resources/fcom-portal/utils';
import EventsPage from '../../../resources/fcom-portal/app/EventsPage.vue';

const listResponse = (events, page = 1, lastPage = 1) => ({ events, current_page: page, last_page: lastPage });
const json = (body, status = 200) => new Response(JSON.stringify(body), { status });

const pending = () => {
    let settle;
    const promise = new Promise(resolve => {
        settle = resolve;
    });
    return { promise, resolve: (body, status) => settle(json(body, status)) };
};

const query = (call = fetch.mock.calls.length - 1) => Object.fromEntries(new URL(fetch.mock.calls[call][0]).searchParams);

// These direct mounts never register ElLoading, so v-loading warns on every mount; drop only that warning.
const warnHandler = (message, instance, trace) => {
    if (!message.includes('Failed to resolve directive: loading')) {
        console.warn(`[Vue warn]: ${message}${trace}`);
    }
};

const eventsPage = async (props = {}) => {
    const router = { push: vi.fn() };
    const wrapper = mount(EventsPage, {
        props: { router, ...props },
        global: { mocks: { $t: t }, config: { warnHandler } }
    });
    await flushPromises();

    return { wrapper, vm: wrapper.vm, router };
};

beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn(async () => json(listResponse([]))));
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-14T12:00:00Z'));
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('EventsPage', () => {
    describe('remembered choices', () => {
        it('opens on the tab, view and calendar mode the member last used', async () => {
            Storage.set('portal_events_tab', 'past');
            Storage.set('portal_events_view', 'cards');
            Storage.set('portal_events_calendar_mode', 'week');

            const { vm } = await eventsPage();

            expect([vm.tab, vm.view, vm.calendarMode]).toEqual(['past', 'cards', 'week']);
        });

        it('ignores remembered choices that no longer exist', async () => {
            Storage.set('portal_events_tab', 'archived');
            Storage.set('portal_events_view', 'kanban');
            Storage.set('portal_events_calendar_mode', 'year');

            const { vm } = await eventsPage();

            expect([vm.tab, vm.view, vm.calendarMode]).toEqual(['upcoming', 'timeline', 'month']);
        });

        it("opens on the admin's default view when the member has not picked one", async () => {
            window.fluentEventFcomPortal.default_view = 'cards';

            expect((await eventsPage()).vm.view).toBe('cards');
        });

        it("keeps the member's own view over the default while switching is allowed", async () => {
            window.fluentEventFcomPortal.default_view = 'cards';
            Storage.set('portal_events_view', 'timeline');

            const { wrapper, vm } = await eventsPage();

            expect(vm.view).toBe('timeline');
            expect(wrapper.findComponent({ name: 'SegmentedSwitch' }).exists()).toBe(true);
        });

        it("uses the admin's view for everyone and hides the switch when it is locked", async () => {
            window.fluentEventFcomPortal.default_view = 'cards';
            window.fluentEventFcomPortal.disable_view_switch = true;
            Storage.set('portal_events_view', 'timeline');

            const { wrapper, vm } = await eventsPage();

            expect(vm.view).toBe('cards');
            expect(wrapper.findComponent({ name: 'SegmentedSwitch' }).exists()).toBe(false);
        });
    });

    describe('loading', () => {
        it("loads the first page of the tab for the member's space", async () => {
            await eventsPage({ spaceSlug: 'design' });

            expect(query()).toEqual({ scope: 'upcoming', space: 'design', search: '', page: '1', per_page: '20' });
        });

        it('loads exactly the visible month grid in calendar view', async () => {
            Storage.set('portal_events_view', 'calendar');

            await eventsPage();

            // October 2026's grid runs Sun 27 Sep to Sat 31 Oct.
            expect(query()).toMatchObject({ from: '2026-09-27', to: '2026-10-31', paginate: 'false' });
        });

        it('appends the next page to the list', async () => {
            fetch.mockResolvedValueOnce(json(listResponse([{ id: 1 }], 1, 2)));
            const { vm } = await eventsPage();

            fetch.mockResolvedValueOnce(json(listResponse([{ id: 2 }], 2, 2)));
            await vm.fetchEvents(2);

            expect(vm.events.map(event => event.id)).toEqual([1, 2]);
            expect(vm.currentPage).toBe(2);
        });

        it('keeps the newest list when an older request answers last', async () => {
            const older = pending();
            const newer = pending();
            fetch.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);
            const { vm } = await eventsPage();

            vm.tab = 'past';
            vm.changeTab();
            newer.resolve(listResponse([{ id: 'past' }]));
            await flushPromises();
            older.resolve(listResponse([{ id: 'upcoming' }]));
            await flushPromises();

            expect(vm.events.map(event => event.id)).toEqual(['past']);
            expect(vm.loading).toBe(false);
        });

        it('drops the error from a request that has been superseded', async () => {
            const older = pending();
            fetch.mockReturnValueOnce(older.promise);
            const { vm } = await eventsPage();

            vm.reload();
            older.resolve({ message: 'Timed out' }, 500);
            await flushPromises();

            expect(vm.error).toBe('');
        });

        it("shows the server's error for the current request", async () => {
            fetch.mockResolvedValueOnce(json({ message: 'Space not found.' }, 404));

            const { wrapper } = await eventsPage({ spaceSlug: 'gone' });

            expect(wrapper.find('[role="alert"]').text()).toBe('Space not found.');
        });

        // The mask itself is checked through the real portal mount in fcom-portal.spec.js (#173).
        it('is loading while the list request is pending', async () => {
            fetch.mockReturnValueOnce(pending().promise);

            const { vm } = await eventsPage();

            expect(vm.loading).toBe(true);
        });
    });

    describe('switching views', () => {
        it('reloads to and from the calendar but not between cards and timeline', async () => {
            const { vm } = await eventsPage();
            const loads = () => fetch.mock.calls.length;
            const start = loads();

            vm.view = 'cards';
            await flushPromises();
            expect(loads()).toBe(start);

            vm.view = 'calendar';
            await flushPromises();
            vm.view = 'timeline';
            await flushPromises();
            expect(loads()).toBe(start + 2);
            expect(Storage.get('portal_events_view')).toBe('timeline');
        });

        it('refetches the calendar only when the month changes', async () => {
            Storage.set('portal_events_view', 'calendar');
            const { vm } = await eventsPage();
            const start = fetch.mock.calls.length;

            vm.calendarDate = '2026-10-20';
            await flushPromises();
            expect(fetch.mock.calls.length).toBe(start);

            vm.calendarDate = '2026-11-02';
            await flushPromises();
            expect(fetch.mock.calls.length).toBe(start + 1);
        });
    });

    describe('events in the list', () => {
        it('keeps the space when an RSVP hands back the event', async () => {
            fetch.mockResolvedValueOnce(json(listResponse([{ id: 5, space_slug: 'design', my_status: null }])));
            const { vm } = await eventsPage();

            vm.applyResponse(vm.events[0], { event: { id: 5, my_status: 'going' } });

            expect(vm.events[0]).toEqual({ id: 5, space_slug: 'design', my_status: 'going' });
        });

        it("opens an event on its page inside its space", async () => {
            const { vm, router } = await eventsPage();

            vm.openEvent({ id: 5, slug: 'community-meetup', space_slug: 'design' });

            expect(router.push).toHaveBeenCalledWith({ name: 'fe_event_view', params: { space: 'design', event_slug: 'community-meetup' } });
        });

        it('offers Create new event, in a space or on all events, to members who may create', async () => {
            expect((await eventsPage({ spaceSlug: 'design' })).vm.canCreate).toBe(true);
            expect((await eventsPage()).vm.canCreate).toBe(true);

            window.fluentEventFcomPortal.can_create_events = false;
            expect((await eventsPage({ spaceSlug: 'design' })).vm.canCreate).toBe(false);
            expect((await eventsPage()).vm.canCreate).toBe(false);
        });

        it('creates in the current space, or lets the composer ask on all events', async () => {
            const inSpace = await eventsPage({ spaceSlug: 'design' });
            inSpace.vm.createEvent();
            expect(inSpace.router.push).toHaveBeenCalledWith({ name: 'fe_create_event', query: { space: 'design' } });

            const allEvents = await eventsPage();
            allEvents.vm.createEvent();
            expect(allEvents.router.push).toHaveBeenCalledWith({ name: 'fe_create_event', query: {} });
        });

        it('explains an empty list by the search, or by the tab', async () => {
            const { vm } = await eventsPage();

            vm.tab = 'past';
            expect(vm.emptyState.title).toBe('No past events');

            vm.search = 'yoga';
            expect(vm.emptyState.title).toBe('No events found');
        });
    });
});
