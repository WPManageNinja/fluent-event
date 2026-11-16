import { describe, it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import Calendars from '@/modules/calendars/index.vue';

const mountList = () => shallowMount(Calendars, {
    global: {
        mixins: [{ methods: { $requestServer: vi.fn(), $changeTitle: vi.fn() } }],
        mocks: { $calendarStatuses: () => [], $router: { push: vi.fn() } }
    }
});

const list = calendars => {
    const wrapper = mountList();
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

// The list is the default; a saved choice wins, and anything else falls back to it.
describe('Calendars view', () => {
    it.each([
        [null, 'list'],
        ['cards', 'cards'],
        ['timeline', 'list'],
    ])('opens in %s view as %s', (saved, view) => {
        localStorage.clear();
        if (saved) localStorage.setItem('fe_calendars_view', JSON.stringify(saved));

        expect(mountList().vm.view).toBe(view);
    });
});
