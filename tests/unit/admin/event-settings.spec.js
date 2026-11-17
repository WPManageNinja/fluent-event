import { describe, it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import EventSettings from '@/modules/events/parts/EventSettings.vue';

const settings = (meta, seats = 0) => shallowMount(EventSettings, {
    props: { event: { id: 7, meta, seats_count: seats } },
    global: {
        mixins: [main],
        renderStubDefaultSlot: true,
        mocks: { $router: { push: vi.fn() } },
    },
});

const rows = wrapper => wrapper.findAll('.fe-event-settings__row')
    .map(row => [row.find('.fe-event-settings__label').text(), row.find('.fe-event-settings__value').text()]);
const seatsLeft = wrapper => wrapper.find('.fe-event-settings__meter-status').text();
const fill = wrapper => wrapper.find('.fe-event-settings__fill').attributes('style');

describe('EventSettings', () => {
    it('lists each setting by its name with what it is set to', () => {
        const wrapper = settings({ entry_type: 'rsvp', capacity: 0, start_display: 'countdown', show_guest_count: 'no' });

        expect(rows(wrapper)).toEqual([
            ['Start Time Display', 'Countdown'],
            ['Entry Type', 'RSVP'],
            ['Capacity', 'Unlimited'],
            ['Guest count', 'Hidden'],
        ]);
        expect(wrapper.find('.fe-event-settings__meter').exists()).toBe(false);
    });

    it('meters the seats taken against the capacity, and counts the ones left', () => {
        const wrapper = settings({ entry_type: 'rsvp', capacity: 10 }, 3);

        expect(seatsLeft(wrapper)).toBe('7 spots left');
        expect(fill(wrapper)).toContain('width: 30%');
        expect(seatsLeft(settings({ entry_type: 'rsvp', capacity: 10 }, 9))).toBe('1 spot left');
    });

    // Admins can add past the capacity, so a full event can hold more than it.
    it('says Full once the seats reach the capacity, and when they pass it', () => {
        expect(seatsLeft(settings({ entry_type: 'open', capacity: 10 }, 10))).toBe('Full');

        const over = settings({ entry_type: 'open', capacity: 10 }, 12);
        expect(seatsLeft(over)).toBe('Full');
        expect(fill(over)).toContain('width: 100%');
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
