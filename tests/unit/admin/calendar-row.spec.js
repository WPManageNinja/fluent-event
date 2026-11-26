import { describe, it, expect, vi } from 'vitest';
import { shallowMount, RouterLinkStub } from '@vue/test-utils';
import main from '@/mixins/main';
import CalendarRow from '@/modules/calendars/parts/CalendarRow.vue';

const row = (calendar = {}) => {
    const requestServer = vi.fn();
    const wrapper = shallowMount(CalendarRow, {
        props: { calendar: { id: 4, title: 'Meetups', slug: 'meetups', status: 'public', meta: { theme_color: '#0099ff' }, ...calendar } },
        global: {
            mixins: [main, { methods: { $requestServer: requestServer } }],
            mocks: { $router: { push: vi.fn() } }
        }
    });

    return { wrapper, requestServer };
};

// The update endpoint validates the whole calendar, so a status change resends the fields it keeps.
describe('CalendarRow status change', () => {
    it('saves the new status with the rest of the calendar and asks the list to reload', () => {
        const { wrapper, requestServer } = row();

        wrapper.vm.changeStatus('draft');
        const request = requestServer.mock.calls[0][0];
        request.onSuccess({});

        expect(request.method).toBe('patch');
        expect(request.path).toBe('calendars/4');
        expect(request.payload).toEqual({ title: 'Meetups', slug: 'meetups', status: 'draft', theme_color: '#0099ff' });
        expect(wrapper.vm.$data).toHaveProperty(request.loadingState);
        expect(wrapper.emitted('updated')).toHaveLength(1);
    });

    it('sends nothing when the status is unchanged', () => {
        const { wrapper, requestServer } = row();

        wrapper.vm.changeStatus('public');

        expect(requestServer).not.toHaveBeenCalled();
    });
});

describe('CalendarRow click', () => {
    const mountRow = () => {
        const push = vi.fn();
        const wrapper = shallowMount(CalendarRow, {
            props: { calendar: { id: 4, title: 'Meetups', slug: 'meetups', status: 'public', meta: {} } },
            global: { mixins: [main], mocks: { $router: { push } }, stubs: { RouterLink: RouterLinkStub } }
        });

        return { wrapper, push };
    };

    it('opens the calendar from anywhere on the row outside its controls', async () => {
        const { wrapper, push } = mountRow();

        await wrapper.find('.fe-calendars__row-slug').trigger('click');

        expect(push).toHaveBeenCalledWith({ name: 'calendar_detail', params: { id: 4 } });
    });

    it('leaves the title link, the status, the counts and the buttons to their own clicks', async () => {
        const { wrapper, push } = mountRow();

        for (const selector of ['a.fe-calendars__row-link', '.fe-calendars__row-status', '.fe-calendars__row-stat', '.fe-calendars__row-actions']) {
            await wrapper.find(selector).trigger('click');
        }

        expect(push).not.toHaveBeenCalled();
    });
});

describe('CalendarRow community link', () => {
    const labels = (wrapper) => wrapper.vm.actions.map(item => item.label);

    it('offers the community space in a new tab when the calendar has one', () => {
        const open = vi.spyOn(window, 'open').mockImplementation(() => null);
        const { wrapper } = row({ space_url: 'https://example.test/portal/space/meetups/home' });

        expect(labels(wrapper)).toEqual(['Edit', 'View in Community']);

        wrapper.vm.actions[1].action();
        expect(open).toHaveBeenCalledWith('https://example.test/portal/space/meetups/home', '_blank', 'noopener');
        open.mockRestore();
    });

    it('leaves the item out without a space or with a non-web link', () => {
        expect(labels(row().wrapper)).toEqual(['Edit']);
        expect(labels(row({ space_url: 'javascript:alert(1)' }).wrapper)).toEqual(['Edit']);
    });
});
