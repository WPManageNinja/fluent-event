import { describe, it, expect } from 'vitest';
import EventGuests from '../../../resources/admin/modules/events/parts/EventGuests.vue';

const { onGuestSaved } = EventGuests.methods;
const list = () => ({
    guests: [{ id: 1, status: 'going' }, { id: 2, status: 'not_going' }],
    counts: { all: 2, going: 1, not_going: 1 },
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

describe('EventGuests empty list', () => {
    const message = query => EventGuests.computed.emptyMessage.call({ query, $t: text => text });

    it('says the event has no guests only when nothing narrows the list', () => {
        expect(message({ search: '', status: '' })).toBe('No guests yet.');
        expect(message({ search: 'jane', status: '' })).toBe('No guests match your search or filter.');
        expect(message({ search: '', status: 'cancelled' })).toBe('No guests match your search or filter.');
    });
});

describe('EventGuests paging after a bulk change', () => {
    // The server's reply for each page the list asks for: 20 Going guests left on page 1, none on page 2.
    const page = number => ({
        guests: { data: number === 1 ? [{ id: 1, status: 'going' }] : [], total: 20, per_page: 20 },
        counts: { all: 21, going: 20, cancelled: 1 },
    });

    it('steps back to the last page when a status change empties the final filtered page', () => {
        const requested = [];
        const list = {
            selectedIds: [9],
            query: { search: '', status: 'going' },
            pagination: { total: 21, per_page: 20, current_page: 2 },
            $requestServer({ payload, onSuccess }) {
                requested.push(payload.page);
                onSuccess(page(payload.page));
            },
        };
        list.fetchGuests = EventGuests.methods.fetchGuests.bind(list);

        EventGuests.methods.onBulkDone.call(list);

        expect(requested).toEqual([2, 1]);
        expect(list.pagination.current_page).toBe(1);
        expect(list.guests).toHaveLength(1);
        expect(list.selectedIds).toEqual([]);
    });
});
