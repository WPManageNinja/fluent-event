import { describe, it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import EventSettings from '@/modules/events/parts/EventSettings.vue';

const settings = meta => shallowMount(EventSettings, {
    props: { event: { id: 7, meta } },
    global: {
        mixins: [main],
        renderStubDefaultSlot: true,
        mocks: { $router: { push: vi.fn() } },
    },
});

const rows = wrapper => wrapper.findAll('.fe-event-settings__row')
    .map(row => [row.find('.fe-event-settings__label').text(), row.find('.fe-event-settings__value').text()]);

describe('EventSettings', () => {
    it('lists each setting by its name with what it is set to', () => {
        const wrapper = settings({ entry_type: 'rsvp', capacity: 0, start_display: 'countdown', show_guest_count: 'no' });

        expect(rows(wrapper)).toEqual([
            ['Start Time Display', 'Countdown'],
            ['Entry Type', 'RSVP'],
            ['Capacity', 'Unlimited'],
            ['Guest count', 'Hidden'],
        ]);
    });

    // Only the number the host set; the guests are on the Guests tab.
    it('shows the capacity alone, with the spots left and waitlist switches beside it', () => {
        const wrapper = settings({ entry_type: 'open', capacity: 10, show_spots_left: 'yes', waitlist: 'yes' });

        expect(rows(wrapper)).toEqual([
            ['Start Time Display', 'Time Left'],
            ['Entry Type', 'Open'],
            ['Capacity', '10'],
            ['Guest count', 'Shown'],
            ['Spots left', 'Shown'],
            ['Waitlist', 'On'],
        ]);
    });

    it('leaves out capacity and guest count for an event nobody registers for', () => {
        const wrapper = settings({ entry_type: 'none', capacity: 10 });

        expect(rows(wrapper)).toEqual([['Start Time Display', 'Time Left'], ['Entry Type', 'None']]);
    });

    it('opens the edit form', async () => {
        const wrapper = settings({ entry_type: 'rsvp' });

        await wrapper.find('.fe-event-settings__header el-button-stub').trigger('click');

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'event_edit', params: { id: 7 } });
    });
});
