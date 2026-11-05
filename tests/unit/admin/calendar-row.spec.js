import { describe, it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import CalendarRow from '@/modules/calendars/parts/CalendarRow.vue';

const row = () => {
    const requestServer = vi.fn();
    const wrapper = shallowMount(CalendarRow, {
        props: { calendar: { id: 4, title: 'Meetups', slug: 'meetups', status: 'public', meta: { theme_color: '#0099ff' } } },
        global: {
            mixins: [main, { methods: { $requestServer: requestServer } }],
            mocks: { $router: { push: vi.fn() } }
        }
    });

    return { wrapper, requestServer, hide: vi.fn() };
};

// The update endpoint validates the whole calendar, so a status change resends the fields it keeps.
describe('CalendarRow status change', () => {
    it('saves the new status with the rest of the calendar and asks the list to reload', () => {
        const { wrapper, requestServer, hide } = row();

        wrapper.vm.changeStatus('draft', hide);
        const request = requestServer.mock.calls[0][0];
        request.onSuccess({});

        expect(hide).toHaveBeenCalled();
        expect(request.method).toBe('patch');
        expect(request.path).toBe('calendars/4');
        expect(request.payload).toEqual({ title: 'Meetups', slug: 'meetups', status: 'draft', theme_color: '#0099ff' });
        expect(wrapper.vm.$data).toHaveProperty(request.loadingState);
        expect(wrapper.emitted('updated')).toHaveLength(1);
    });

    it('sends nothing when the status is unchanged', () => {
        const { wrapper, requestServer, hide } = row();

        wrapper.vm.changeStatus('public', hide);

        expect(hide).toHaveBeenCalled();
        expect(requestServer).not.toHaveBeenCalled();
    });
});
