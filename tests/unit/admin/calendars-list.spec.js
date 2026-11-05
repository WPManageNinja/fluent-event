import { describe, it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import Calendars from '@/modules/calendars/index.vue';

const list = calendars => {
    const wrapper = shallowMount(Calendars, {
        global: {
            mixins: [{ methods: { $requestServer: vi.fn(), $changeTitle: vi.fn() } }],
            mocks: { $calendarStatuses: () => [], $router: { push: vi.fn() } }
        }
    });
    wrapper.vm.calendars = calendars;

    return wrapper.vm.groups.map(group => [group.key, group.calendars.map(calendar => calendar.id)]);
};

// A calendar linked to a FluentCommunity space lists under its own heading, apart from the site's.
describe('Calendars groups', () => {
    it('splits site calendars from FluentCommunity calendars', () => {
        expect(list([
            { id: 1, meta: { theme_color: '#0099ff' } },
            { id: 2, meta: { fcom_space_id: 26 } },
            { id: 3, meta: {} },
        ])).toEqual([['site', [1, 3]], ['community', [2]]]);
    });

    it('leaves out a group with no calendars', () => {
        expect(list([{ id: 2, meta: { fcom_space_id: 26 } }])).toEqual([['community', [2]]]);
    });
});
