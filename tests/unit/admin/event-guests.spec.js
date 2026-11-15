import { describe, it, expect, vi } from 'vitest';
import EventGuests from '../../../resources/admin/modules/events/parts/EventGuests.vue';

const { onGuestSaved, onGuestDeleted } = EventGuests.methods;
const list = () => ({
    guests: [{ id: 1, status: 'going' }, { id: 2, status: 'not_going' }],
    counts: { all: 2, going: 1, not_going: 1 },
    $emit: vi.fn(),
});

describe('EventGuests tab counts', () => {
    it('moves one guest between status counts and keeps the row in place', () => {
        const page = list();

        onGuestSaved.call(page, { id: 1, status: 'checked_in' });

        expect(page.counts).toEqual({ all: 2, going: 0, not_going: 1, checked_in: 1 });
        expect(page.guests.map(guest => [guest.id, guest.status])).toEqual([[1, 'checked_in'], [2, 'not_going']]);
    });

    it('adds a new guest to the top and to the All and status counts', () => {
        const page = list();

        onGuestSaved.call(page, { id: 3, status: 'going' });

        expect(page.counts).toEqual({ all: 3, going: 2, not_going: 1 });
        expect(page.guests[0].id).toBe(3);
    });

    it('leaves the counts alone when an edit keeps the status', () => {
        const page = list();

        onGuestSaved.call(page, { id: 2, status: 'not_going', name: 'Renamed' });

        expect(page.counts).toEqual({ all: 2, going: 1, not_going: 1 });
        expect(page.guests[1].name).toBe('Renamed');
    });
});

// After the write lands, so the event page's guest numbers never read the old counts.
describe('EventGuests changes', () => {
    it('tells the event page once a guest is saved', () => {
        const page = list();

        onGuestSaved.call(page, { id: 1, status: 'checked_in' });

        expect(page.$emit).toHaveBeenCalledWith('changed');
    });

    it('tells the event page once a guest is deleted', () => {
        const page = { ...list(), goToLastPageIfEmptied: vi.fn(), fetchGuests: vi.fn() };

        onGuestDeleted.call(page);

        expect(page.$emit).toHaveBeenCalledWith('changed');
    });
});

describe('EventGuests empty list', () => {
    const message = query => EventGuests.computed.emptyMessage.call({ query, $t: text => text });

    it('says the event has no guests only when nothing narrows the list', () => {
        expect(message({ search: '', status: '' })).toBe('No guests yet.');
        expect(message({ search: 'jane', status: '' })).toBe('No guests match your search or filter.');
        expect(message({ search: '', status: 'cancelled' })).toBe('No guests match your search or filter.');
    });
});
