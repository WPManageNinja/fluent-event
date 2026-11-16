import { describe, it, expect } from 'vitest';
import EventDetail from '@/modules/events/detail.vue';

const { computed } = EventDetail;

// The real $t fills %s / %d in order and %1$s by position.
const t = (text, ...args) => {
    let index = 0;

    return text.replace(/%(?:(\d+)\$)?[sd]/g, (match, position) => args[position ? position - 1 : index++]);
};

const overview = (event, registrationWindow = null) => {
    const ctx = {
        event,
        registrationWindow,
        $t: t,
        $_n: (single, plural, count) => t(count === 1 ? single : plural, count),
        $formatDateTime: value => `[${value}]`
    };

    ['entryType', 'takesRegistrations', 'capacity'].forEach(name => {
        Object.defineProperty(ctx, name, { get: () => computed[name].call(ctx) });
    });

    return {
        title: () => computed.registrationTitle.call(ctx),
        // A badge reads as [Off] beside its label, a success badge as [On+].
        text: () => computed.registrationNotes.call(ctx)
            .map(note => (note.badge ? `${note.text} [${note.badge}${note.success ? '+' : ''}]` : note.text))
            .join(' · '),
        window: () => computed.windowRow.call(ctx)
    };
};

// The Overview's registration rows: the event's registration settings, read without opening the form.
describe('Event page registration', () => {
    it('names the entry type and counts seats against the capacity', () => {
        expect(overview({ seats_count: 6, meta: { entry_type: 'open', capacity: 8 } }).title()).toBe('Open registration · 6 of 8 going');
        expect(overview({ seats_count: 6, meta: { entry_type: 'rsvp', capacity: '8' } }).title()).toBe('RSVP · 6 of 8 going');
        expect(overview({ seats_count: 6, meta: { entry_type: 'rsvp', capacity: 0 } }).title()).toBe('RSVP · 6 going');
        expect(overview({ meta: { entry_type: 'open' } }).title()).toBe('Open registration · 0 going');
    });

    it('says how many spots are left, the waitlist and guest count as badges, success when on or shown', () => {
        expect(overview({ seats_count: 6, meta: { entry_type: 'open', capacity: 8, show_guest_count: 'yes' } }).text())
            .toBe('2 spots left · Waitlist [Off] · Guest count [Shown+]');
        expect(overview({ seats_count: 7, meta: { entry_type: 'open', capacity: 8, waitlist: 'yes' } }).text())
            .toBe('1 spot left · Waitlist [On+] · Guest count [Hidden]');
    });

    it('says Full once the seats are taken, with how many are waiting', () => {
        expect(overview({ seats_count: 8, waitlisted_count: 3, meta: { entry_type: 'open', capacity: 8, waitlist: 'yes' } }).text())
            .toBe('Full · Waitlist [On+] · 3 waiting · Guest count [Hidden]');
        expect(overview({ seats_count: 10, meta: { entry_type: 'rsvp', capacity: 8 } }).text())
            .toBe('Full · Waitlist [Off] · Guest count [Hidden]');
    });

    it('says there is no limit, and leaves the waitlist out, without a capacity', () => {
        expect(overview({ seats_count: 6, meta: { entry_type: 'rsvp', waitlist: 'yes', show_guest_count: 'yes' } }).text())
            .toBe('No limit · Guest count [Shown+]');
    });

    // Guests waitlisted before the waitlist, capacity or entry type changed are still waiting.
    it('shows waiting guests whatever the settings are now', () => {
        expect(overview({ seats_count: 6, waitlisted_count: 2, meta: { entry_type: 'open', capacity: 8, waitlist: 'no' } }).text())
            .toBe('2 spots left · Waitlist [Off] · 2 waiting · Guest count [Hidden]');
        expect(overview({ waitlisted_count: 1, meta: { entry_type: 'rsvp', capacity: 0 } }).text())
            .toBe('No limit · 1 waiting · Guest count [Hidden]');
        expect(overview({ waitlisted_count: 2, meta: { entry_type: 'none' } }).text())
            .toBe('Nobody registers for this event; admins can still add guests. · 2 waiting');
    });

    it('says nobody registers for a view-only event, and shows no window', () => {
        const event = { meta: { entry_type: 'none' } };

        expect(overview(event).title()).toBe('No registration');
        expect(overview(event).text()).toBe('Nobody registers for this event; admins can still add guests.');
        expect(overview(event, { state: 'open', closes_at: null }).window()).toBe(null);
        expect(overview({ meta: {} }).title()).toBe('No registration');
    });

    it('describes the registration window from the state the server worked out', () => {
        const event = { meta: { entry_type: 'rsvp' } };

        expect(overview(event, { state: 'open', closes_at: null }).window()).toEqual({ title: 'Registration open', text: 'Until the event ends' });
        expect(overview(event, { state: 'open', closes_at: '2026-11-19 18:00:00' }).window()).toEqual({ title: 'Registration open', text: 'Closes [2026-11-19 18:00:00]' });
        expect(overview(event, { state: 'not_open', opens_at: '2026-11-18 09:00:00' }).window()).toEqual({ title: 'Registration not open yet', text: 'Opens [2026-11-18 09:00:00]' });
        expect(overview(event, { state: 'closed', closes_at: '2026-11-17 14:00:00' }).window()).toEqual({ title: 'Registration closed', text: 'Closed [2026-11-17 14:00:00]' });
        expect(overview(event, { state: 'ended' }).window()).toEqual({ title: 'Registration closed', text: 'The event has ended' });
        expect(overview(event, null).window()).toBe(null);
    });
});
