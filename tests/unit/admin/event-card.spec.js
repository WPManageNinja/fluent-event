import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import main from '@/mixins/main';
import EventCard from '@/modules/events/parts/EventCard.vue';

const card = (overrides = {}, meta = {}) => mount(EventCard, {
    props: {
        event: {
            id: 1,
            title: 'Meetup',
            status: 'public',
            venue_type: 'online',
            start_at_site: '2026-11-20 18:00:00',
            end_at_site: '2026-11-20 20:00:00',
            meta: { entry_type: 'rsvp', ...meta },
            ...overrides
        }
    },
    global: {
        mixins: [main],
        stubs: { RouterLink: { template: '<a><slot /></a>' } }
    }
});

// The list's "N guests": every guest of the event, at a glance, without opening it.
describe('EventCard', () => {
    // A view-only event still shows its count: guests from an earlier entry type, or added by an admin, stay its guests.
    it.each(['rsvp', 'open', 'none'])('shows how many guests an %s event has', (entryType) => {
        const wrapper = card({ guests_count: 12 }, { entry_type: entryType });

        expect(wrapper.find('.fe-event-card__guests').text()).toBe('12 guests');
    });

    it('uses the singular for one guest', () => {
        expect(card({ guests_count: 1 }).find('.fe-event-card__guests').text()).toBe('1 guest');
    });

    it('shows 0 guests while an event has none yet', () => {
        expect(card({ guests_count: 0 }).find('.fe-event-card__guests').text()).toBe('0 guests');
    });
});
