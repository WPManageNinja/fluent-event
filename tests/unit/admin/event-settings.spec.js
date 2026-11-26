import { describe, it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import EventSettings from '@/modules/events/parts/EventSettings.vue';

const settings = (meta, registrationWindow = null) => shallowMount(EventSettings, {
    props: { event: { id: 7, meta }, registrationWindow },
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
            ['Registration opens', 'Immediately'],
            ['Registration closes', 'When the event ends'],
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
            ['Registration opens', 'Immediately'],
            ['Registration closes', 'When the event ends'],
        ]);
    });

    it('leaves out capacity and guest count for an event nobody registers for', () => {
        const wrapper = settings({ entry_type: 'none', capacity: 10 });

        expect(rows(wrapper)).toEqual([['Start Time Display', 'Time Left'], ['Entry Type', 'None']]);
    });

    // The moments come worked out from the server, in site time.
    it('shows when registration opens and closes', () => {
        const wrapper = settings({ entry_type: 'rsvp' }, { state: 'open', opens_at: '2026-11-03 10:00:00', closes_at: '2026-11-10 08:00:00' });
        const window = Object.fromEntries(rows(wrapper));

        expect(window['Registration opens']).toBe(wrapper.vm.$formatDateTime('2026-11-03 10:00:00'));
        expect(window['Registration closes']).toBe(wrapper.vm.$formatDateTime('2026-11-10 08:00:00'));
    });

    // The site's 2:30 AM, read by a New York browser whose clocks skip 2 AM that night, must not show as 3:30.
    it("shows the site's times as sent, whatever the browser's own DST does", () => {
        const tz = process.env.TZ;
        process.env.TZ = 'America/New_York';

        try {
            const wrapper = settings({ entry_type: 'rsvp' }, { state: 'not_open', opens_at: '2027-03-14 02:30:00', closes_at: '2027-03-14 02:45:00' });
            const window = Object.fromEntries(rows(wrapper));

            expect(window['Registration opens']).toMatch(/\b2:30\b/);
            expect(window['Registration closes']).toMatch(/\b2:45\b/);
        } finally {
            process.env.TZ = tz;
        }
    });

    it('opens the edit form', async () => {
        const wrapper = settings({ entry_type: 'rsvp' });

        await wrapper.find('.fe-event-settings__header el-button-stub').trigger('click');

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'event_edit', params: { id: 7 } });
    });
});
