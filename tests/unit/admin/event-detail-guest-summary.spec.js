import { describe, it, expect, vi } from 'vitest';
import EventDetail from '@/modules/events/detail.vue';

const { computed, methods } = EventDetail;

const label = (event) => computed.guestCountLabel.call({
    event,
    $_n: (single, plural, count) => (count === 1 ? single : plural).replace('%d', count)
});

// The Overview's "N guests" comes with the event, counted as on the list cards.
describe('Event page guest count', () => {
    it('shows every guest of the event, as the list cards do', () => {
        expect(label({ guests_count: 12 })).toBe('12 guests');
        expect(label({ guests_count: 1 })).toBe('1 guest');
    });

    it('shows 0 guests while the event has none', () => {
        expect(label({ guests_count: 0 })).toBe('0 guests');
        expect(label({})).toBe('0 guests');
    });

    it('loads the event and its count with a single request', () => {
        const ctx = { $route: { params: { id: 7 } }, $requestServer: vi.fn(), $changeTitle: vi.fn() };

        methods.fetchEvent.call(ctx);
        ctx.$requestServer.mock.calls[0][0].onSuccess({ event: { title: 'Meetup', guests_count: 4 }, public_url: '' });

        expect(ctx.$requestServer).toHaveBeenCalledTimes(1);
        expect(ctx.$requestServer.mock.calls[0][0].path).toBe('events/7');
        expect(ctx.event.guests_count).toBe(4);
    });
});
