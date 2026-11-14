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

// The list's "N going": the guests coming, at a glance, without opening each event.
describe('EventCard', () => {
    it.each(['rsvp', 'open'])('shows how many are going to an %s event', (entryType) => {
        const wrapper = card({ going_count: 12 }, { entry_type: entryType });

        expect(wrapper.find('.fe-event-card__going').text()).toBe('12 going');
    });

    it('shows 0 going while an event takes guests but has none yet', () => {
        expect(card({ going_count: 0 }).find('.fe-event-card__going').text()).toBe('0 going');
    });

    it('shows no count for a view-only event, where nobody registers', () => {
        expect(card({ going_count: 0 }, { entry_type: 'none' }).find('.fe-event-card__going').exists()).toBe(false);
    });
});
