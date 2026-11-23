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

describe('EventGuests after a bulk action', () => {
    // The server's reply for each page the list asks for: 20 guests left on page 1, none on page 2.
    const page = number => ({
        guests: { data: number === 1 ? [{ id: 1, status: 'going' }] : [], total: 20, per_page: 20 },
        counts: { all: 20, going: 20 },
    });

    it('steps back to the last page when a bulk delete empties the final page', () => {
        const requested = [];
        const list = {
            bulk: { action: 'delete' },
            selectedIds: [9],
            query: { search: '', status: 'going' },
            pagination: { total: 21, per_page: 20, current_page: 2 },
            $requestServer({ payload, onSuccess }) {
                requested.push(payload.page);
                onSuccess(page(payload.page));
            },
        };
        list.fetchGuests = EventGuests.methods.fetchGuests.bind(list);

        EventGuests.methods.onBulkDone.call(list, { message: '1 guest has been deleted.' });

        expect(requested).toEqual([2, 1]);
        expect(list.pagination.current_page).toBe(1);
        expect(list.selectedIds).toEqual([]);
    });

    it('updates changed rows and tab counts in place on a status change, without reloading', () => {
        const guestList = {
            ...list(),
            bulk: { action: 'change_status', status: 'cancelled' },
            selectedIds: [1, 2],
            fetchGuests: () => { throw new Error('should not reload'); },
        };
        guestList.onGuestSaved = EventGuests.methods.onGuestSaved.bind(guestList);

        EventGuests.methods.onBulkDone.call(guestList, { guests: [{ id: 1, status: 'cancelled' }, { id: 2, status: 'cancelled' }] });

        expect(guestList.guests.map(guest => [guest.id, guest.status])).toEqual([[1, 'cancelled'], [2, 'cancelled']]);
        expect(guestList.counts).toEqual({ all: 2, going: 0, not_going: 0, cancelled: 2 });
        expect(guestList.selectedIds).toEqual([]);
    });
});
