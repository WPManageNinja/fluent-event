import { describe, it, expect, vi, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import Storage from '@/utils/Storage';
import EventsView from '@/modules/events/parts/EventsView.vue';

const view = (props = {}) => {
    const requestServer = vi.fn();
    const wrapper = shallowMount(EventsView, {
        props,
        global: { mixins: [main, { methods: { $requestServer: requestServer } }] }
    });

    return { wrapper, requestServer, lastPayload: () => requestServer.mock.calls.at(-1)[0].payload };
};

afterEach(() => {
    vi.useRealTimers();
});

describe('EventsView', () => {
    describe('choosing the view', () => {
        it('reopens the view the member last picked', () => {
            Storage.set('events_view', 'cards');

            expect(view().wrapper.vm.view).toBe('cards');
        });

        it("shows the site default when the site locks the switch, whatever was picked", () => {
            Storage.set('events_view', 'cards');
            window.fluentEventAdmin.general_settings = { default_events_view: 'calendar', disable_event_view_switch: 'yes' };

            expect(view().wrapper.vm.view).toBe('calendar');
        });

        it('ignores a remembered view that no longer exists', () => {
            Storage.set('events_view', 'kanban');
            window.fluentEventAdmin.general_settings.default_events_view = 'cards';

            expect(view().wrapper.vm.view).toBe('cards');
        });

        it('falls back to the timeline when the site has no default', () => {
            window.fluentEventAdmin.general_settings = {};

            expect(view().wrapper.vm.view).toBe('timeline');
        });
    });

    describe('loading events', () => {
        it('loads one page of the period for the list views', () => {
            const { lastPayload } = view({ period: 'past' });

            expect(lastPayload()).toEqual({
                search: '',
                status: '',
                calendar_id: '',
                page: 1,
                per_page: 10,
                period: 'past',
                start_date: '',
                end_date: ''
            });
        });

        it('loads the whole month grid, padded a day each side, for the calendar', () => {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2026-10-14T12:00:00Z'));
            Storage.set('events_view', 'calendar');

            const { lastPayload } = view();

            // October 2026's grid runs Sun 27 Sep to Sat 31 Oct.
            expect(lastPayload()).toMatchObject({
                paginate: false,
                start_date: '2026-09-26',
                end_date: '2026-11-01'
            });
            expect(lastPayload()).not.toHaveProperty('page');
        });

        it("scopes to the calendar page's own calendar over the filter", async () => {
            const { wrapper, lastPayload } = view({ calendarId: 7 });
            wrapper.vm.query.calendar_id = 3;
            wrapper.vm.refetch();

            expect(lastPayload().calendar_id).toBe(7);
        });

        it('starts again from page one when the view changes', async () => {
            const { wrapper, requestServer, lastPayload } = view();
            wrapper.vm.pagination.current_page = 4;

            wrapper.vm.view = 'cards';
            await wrapper.vm.$nextTick();

            expect(requestServer).toHaveBeenCalledTimes(2);
            expect(lastPayload().page).toBe(1);
        });

        it('reads the paginated list and its total from the response', () => {
            const { wrapper, requestServer } = view();

            requestServer.mock.calls[0][0].onSuccess({ events: { data: [{ id: 1 }], total: 31, per_page: '10' } });

            expect(wrapper.vm.events).toEqual([{ id: 1 }]);
            expect(wrapper.vm.pagination.total).toBe(31);
            expect(wrapper.vm.pagination.per_page).toBe(10);
        });
    });

    describe('filters', () => {
        it('lists each applied filter with a readable value', () => {
            const { wrapper } = view();
            wrapper.vm.calendars = [{ id: 3, title: 'Workshops' }];
            wrapper.vm.query = { search: '', status: 'private', calendar_id: 3, dates: ['2026-10-01', '2026-10-31'] };

            expect(wrapper.vm.activeFilters).toEqual([
                { key: 'status', label: 'Status', value: 'Private' },
                { key: 'calendar_id', label: 'Calendar', value: 'Workshops' },
                { key: 'dates', label: 'Date', value: 'Oct 1, 2026 – Oct 31, 2026' }
            ]);
        });

        it('shows a calendar id until the calendar list has loaded', () => {
            const { wrapper } = view();
            wrapper.vm.query.calendar_id = 3;

            expect(wrapper.vm.activeFilters[0].value).toBe(3);
        });

        it('drops the date range in calendar view, where the grid sets the dates', () => {
            Storage.set('events_view', 'calendar');
            const { wrapper } = view();
            wrapper.vm.query.dates = ['2026-10-01', '2026-10-31'];

            expect(wrapper.vm.activeFilters).toEqual([]);
        });

        it('clears one filter and reloads', () => {
            const { wrapper, requestServer } = view();
            wrapper.vm.query.status = 'draft';

            wrapper.vm.removeFilter('status');

            expect(wrapper.vm.query.status).toBe('');
            expect(requestServer).toHaveBeenCalledTimes(2);
        });
    });

    describe('empty state', () => {
        it('blames the search or filters when they are set', () => {
            const { wrapper } = view();
            wrapper.vm.query.search = 'yoga';

            expect(wrapper.vm.emptyState.title).toBe('No events found');
        });

        it('speaks to the period being shown', () => {
            expect(view({ period: 'past' }).wrapper.vm.emptyState.title).toBe('No past events');
            expect(view({ period: 'upcoming' }).wrapper.vm.emptyState.title).toBe('No upcoming events');
            expect(view({ period: 'all' }).wrapper.vm.emptyState.title).toBe('No events yet');
        });
    });
});
