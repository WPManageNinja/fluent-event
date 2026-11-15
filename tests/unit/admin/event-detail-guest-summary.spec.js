import { describe, it, expect, vi } from 'vitest';
import EventDetail from '@/modules/events/detail.vue';

const { computed, methods, watch } = EventDetail;

const summary = (counts) => computed.guestSummary.call({
    guestCounts: counts,
    $t: (text, count) => text.replace('%d', count)
});

const counts = (going, checkedIn) => ({ all: going + checkedIn, going, not_going: 2, checked_in: checkedIn, cancelled: 0 });

// The Overview's guest numbers: going counts everyone coming, as the list cards do.
describe('Event page guest summary', () => {
    it('counts checked-in guests as going and names them too', () => {
        expect(summary(counts(9, 3))).toBe('12 going · 3 checked in');
    });

    it('shows only going while nobody has checked in, zero included', () => {
        expect(summary(counts(5, 0))).toBe('5 going');
        expect(summary(counts(0, 0))).toBe('0 going');
    });

    it('shows nothing before the counts arrive', () => {
        expect(summary(null)).toBe('');
    });

    it('asks for no counts on a view-only event, where nobody registers', () => {
        const ctx = { event: { meta: { entry_type: 'none' } }, $requestServer: vi.fn() };

        methods.fetchGuestCounts.call(ctx);

        expect(ctx.$requestServer).not.toHaveBeenCalled();
    });

    it("reads the counts from the guests list, without touching the page's own loading state", () => {
        const ctx = { event: { meta: { entry_type: 'open' } }, $route: { params: { id: 7 } }, $requestServer: vi.fn() };

        methods.fetchGuestCounts.call(ctx);

        expect(ctx.$requestServer).toHaveBeenCalledWith(expect.objectContaining({
            path: 'events/7/guests', payload: { per_page: 1 }, loadingState: null, errorState: null
        }));
        ctx.$requestServer.mock.calls[0][0].onSuccess({ counts: counts(1, 1) });
        expect(ctx.guestCounts).toEqual(counts(1, 1));
    });

    it('reads the counts again on returning to Overview, after guests may have changed', () => {
        const ctx = { fetchGuestCounts: vi.fn() };

        watch.tab.call(ctx, 'guests');
        expect(ctx.fetchGuestCounts).not.toHaveBeenCalled();

        watch.tab.call(ctx, 'overview');
        expect(ctx.fetchGuestCounts).toHaveBeenCalledTimes(1);
    });
});
