import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import CalendarDangerZone from '@/modules/calendars/parts/CalendarDangerZone.vue';

// A calendar delete takes every event in it and their guests (CleanupHandler::cleanCalendar).
describe('CalendarDangerZone', () => {
    it('warns that the events and their guests go with the calendar, on the page and in the confirm', async () => {
        const $confirm = vi.fn(() => new Promise(() => {}));
        const wrapper = mount(CalendarDangerZone, {
            props: { calendar: { id: 5, title: 'Workshops' } },
            global: { mocks: { $confirm } }
        });
        const warning = 'Deleting this calendar also permanently deletes all of its events and their guests. This cannot be undone.';

        expect(wrapper.find('.fe-danger-zone__text').text()).toBe(warning);

        await wrapper.setData({ typedTitle: 'Workshops' });
        wrapper.vm.promptDelete();
        expect($confirm.mock.calls[0][0]).toBe(warning);
    });
});
