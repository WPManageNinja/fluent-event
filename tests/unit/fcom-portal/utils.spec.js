import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
    t,
    eventsApi,
    communityApi,
    errorMessage,
    eventPhase,
    timeFormat,
    dateFormat,
    eventStart,
    eventMonth,
    eventDay,
    timezoneOffset,
    isSeated,
    hasEventActions,
    guestCountLabel,
    setPageTitle
} from '../../../resources/fcom-portal/utils';

const respond = (body, status = 200) => new Response(typeof body === 'string' ? body : JSON.stringify(body), { status });
const sent = () => {
    const [url, options] = fetch.mock.calls.at(-1);
    return { url, options };
};

describe('t', () => {
    it('returns the English text when the portal has no translation', () => {
        expect(t('Create Event')).toBe('Create Event');
    });

    it("uses the portal's own strings", () => {
        window.fluentEventFcomPortal.i18n = { 'Create Event': 'Veranstaltung erstellen' };

        expect(t('Create Event')).toBe('Veranstaltung erstellen');
    });

    it('fills placeholders in order or by position', () => {
        window.fluentEventFcomPortal.i18n = { '%1$s – %2$s in your time': '%2$s ← %1$s (Ihre Zeit)' };

        expect(t('%s going', 4)).toBe('4 going');
        expect(t('%1$s – %2$s in your time', '9 AM', '10 AM')).toBe('10 AM ← 9 AM (Ihre Zeit)');
    });

    it('keeps a placeholder whose argument is missing', () => {
        // Unlike the admin $t, which renders a missing argument as nothing.
        expect(t('%s going')).toBe('%s going');
    });

    it('works before the portal vars are printed', () => {
        delete window.fluentEventFcomPortal;

        expect(t('Day %d', 2)).toBe('Day 2');
    });
});

describe('portal REST client', () => {
    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn(async () => respond({ ok: true })));
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it("sends fcom's nonce for a logged-in member, read fresh on every call", async () => {
        await eventsApi.get('community/events');
        window.fluentComAdmin.rest.nonce = 'renewed-nonce';
        await eventsApi.get('community/events');

        expect(sent().options.headers['X-WP-Nonce']).toBe('renewed-nonce');
        expect(sent().options.credentials).toBe('same-origin');
    });

    it('sends no nonce for a logged-out visitor', async () => {
        window.fluentEventFcomPortal.is_logged_in = false;

        await eventsApi.get('community/events');

        expect(sent().options.headers).not.toHaveProperty('X-WP-Nonce');
    });

    it('puts GET data in the query string', async () => {
        await eventsApi.get('community/events', { scope: 'upcoming', page: 2 });

        expect(sent().url).toBe('https://example.test/wp-json/fluent-event/v2/community/events?scope=upcoming&page=2');
        expect(sent().options.body).toBeUndefined();
    });

    it('works on a site without pretty permalinks', async () => {
        window.fluentEventFcomPortal.rest_url = 'https://example.test/?rest_route=/fluent-event/v2/';

        await eventsApi.get('community/events', { scope: 'past' });

        expect(sent().url).toBe('https://example.test/?rest_route=/fluent-event/v2/community/events&scope=past');
    });

    it('posts JSON with a content type', async () => {
        await eventsApi.post('community/events/5/register', { status: 'going' });

        expect(sent().options).toMatchObject({ method: 'POST', body: '{"status":"going"}' });
        expect(sent().options.headers['Content-Type']).toBe('application/json');
    });

    it('posts FormData untouched so the browser sets the multipart boundary', async () => {
        const form = new FormData();
        form.append('file', 'cover');

        await communityApi.post('feeds/media-upload', form);

        expect(sent().url).toBe('https://example.test/wp-json/fluent-community/v2/feeds/media-upload');
        expect(sent().options.body).toBe(form);
        expect(sent().options.headers).not.toHaveProperty('Content-Type');
    });

    it('throws the error body on a failed response', async () => {
        fetch.mockResolvedValueOnce(respond({ message: 'Registration is closed.' }, 403));

        await expect(eventsApi.post('community/events/5/register', {})).rejects.toEqual({ message: 'Registration is closed.' });
    });

    it('throws an empty object when the error body is not JSON', async () => {
        fetch.mockResolvedValueOnce(respond('<html>Bad gateway</html>', 502));

        await expect(eventsApi.get('community/events')).rejects.toEqual({});
    });
});

describe('errorMessage', () => {
    it("prefers the server's message", () => {
        expect(errorMessage({ message: 'Registration is closed.' })).toBe('Registration is closed.');
    });

    it('takes the first rule of the first invalid field', () => {
        expect(errorMessage({ errors: { title: { required: 'Title is required.' }, venue: { max: 'Too long.' } } }))
            .toBe('Title is required.');
    });

    it("reads fcom's validation of its own field named message", () => {
        expect(errorMessage({ message: { required: 'Please write something.' } })).toBe('Please write something.');
    });

    it('falls back to a translated default', () => {
        window.fluentEventFcomPortal.i18n = { 'Something went wrong. Please try again.': 'Etwas ist schiefgelaufen.' };

        expect(errorMessage({})).toBe('Etwas ist schiefgelaufen.');
        expect(errorMessage(null)).toBe('Etwas ist schiefgelaufen.');
    });
});

describe('event time helpers', () => {
    const meetup = { start_at: '2026-10-20 12:00:00', end_at: '2026-10-20 14:00:00', start_at_site: '2026-10-20 18:00:00' };

    afterEach(() => {
        vi.useRealTimers();
    });

    it('is upcoming before the start, live from it and ended from the end', () => {
        vi.useFakeTimers({ toFake: ['Date'] });

        vi.setSystemTime(new Date('2026-10-20T11:59:59Z'));
        expect(eventPhase(meetup)).toBe('upcoming');

        vi.setSystemTime(new Date('2026-10-20T12:00:00Z'));
        expect(eventPhase(meetup)).toBe('live');

        vi.setSystemTime(new Date('2026-10-20T14:00:00Z'));
        expect(eventPhase(meetup)).toBe('ended');
    });

    it("trusts the server's is_past over the clock", () => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-10-01T00:00:00Z'));

        expect(eventPhase({ ...meetup, is_past: true })).toBe('ended');
    });

    it("formats the start in fcom's date and time format", () => {
        expect(eventStart(meetup)).toBe('October 20, 2026 · 6:00 PM');

        window.fluentComAdmin.date_formatter = 'DD/MM/YYYY';
        window.fluentComAdmin.time_formatter = 'HH:mm';
        expect(dateFormat()).toBe('DD/MM/YYYY');
        expect(timeFormat()).toBe('HH:mm');
        expect(eventStart(meetup)).toBe('20/10/2026 · 18:00');
    });

    it('falls back to a 12-hour clock and a short date without fcom vars', () => {
        delete window.fluentComAdmin;

        expect(timeFormat()).toBe('h:mm A');
        expect(dateFormat()).toBe('MMM D, YYYY');
    });

    it('splits the start into a month and unpadded day for the date chip', () => {
        expect(eventMonth({ start_at_site: '2026-10-04 09:00:00' })).toBe('Oct');
        expect(eventDay({ start_at_site: '2026-10-04 09:00:00' })).toBe('4');
    });

    it("labels a zone's offset as it will be on the event date", () => {
        expect(timezoneOffset('Asia/Dhaka')).toBe('GMT+6');
        expect(timezoneOffset('America/New_York', new Date('2026-01-15T12:00:00Z'))).toBe('GMT-5');
        expect(timezoneOffset('America/New_York', new Date('2026-07-15T12:00:00Z'))).toBe('GMT-4');
    });

    it('returns an empty label for an unknown zone', () => {
        expect(timezoneOffset('Mars/Olympus_Mons')).toBe('');
    });
});

describe('RSVP helpers', () => {
    it('counts a checked-in guest as seated', () => {
        expect(isSeated({ my_status: 'going' })).toBe(true);
        expect(isSeated({ my_status: 'checked_in' })).toBe(true);
        expect(isSeated({ my_status: 'not_going' })).toBe(false);
        expect(isSeated({ my_status: null })).toBe(false);
    });

    it('offers actions on RSVP and open events, and on any past event', () => {
        expect(hasEventActions({ entry_type: 'rsvp' })).toBe(true);
        expect(hasEventActions({ entry_type: 'open' })).toBe(true);
        expect(hasEventActions({ entry_type: 'none' })).toBe(false);
        expect(hasEventActions({ entry_type: 'none', is_past: true })).toBe(true);
    });

    it('shows the going count the server sends, zero included', () => {
        expect(guestCountLabel({ entry_type: 'rsvp', guest_count: 0 })).toBe('0 going');
        expect(guestCountLabel({ entry_type: 'open', guest_count: 12 })).toBe('12 going');
    });

    it('hides the count when the server withholds it', () => {
        expect(guestCountLabel({ entry_type: 'rsvp', guest_count: null })).toBe('');
        expect(guestCountLabel({ entry_type: 'none' })).toBe('');
    });
});

describe('setPageTitle', () => {
    it("titles the tab the way fcom titles its own pages", () => {
        window.fluentComAdmin.site_title = 'Acme Community';
        setPageTitle('All Events');

        expect(document.title).toBe('All Events - Acme Community');
    });

    it('uses the title alone when the site has none', () => {
        window.fluentComAdmin.site_title = '';
        setPageTitle('Create Event');

        expect(document.title).toBe('Create Event');
    });
});
