import { describe, it, expect, vi, afterEach } from 'vitest';
import { DAY_MS, toUtcDay, toDayKey, daySpan, icsStamp, buildIcs, downloadIcs } from '@/utils/date';

const encoder = new TextEncoder();
const bytes = (line) => encoder.encode(line).length;
const unfold = (text) => text.replace(/\r\n /g, '');
const property = (text, name) => unfold(text).split('\r\n').find(line => line.startsWith(`${name}:`));

const event = (overrides = {}) => ({
    id: 42,
    title: 'Community meetup',
    start_at: '2026-10-13 11:30:00',
    end_at: '2026-10-13 13:00:00',
    description: 'Bring a friend.',
    location: 'Dhaka',
    url: 'https://example.test/portal/post/community-meetup/event',
    ...overrides
});

describe('day arithmetic', () => {
    it('reads only the date part of a stored datetime', () => {
        expect(toUtcDay('2026-10-13 23:59:59')).toBe(Date.UTC(2026, 9, 13));
    });

    it('turns a day back into its YYYY-MM-DD key', () => {
        expect(toDayKey(toUtcDay('2026-03-08 10:00:00'))).toBe('2026-03-08');
    });

    it('steps across a year boundary one day at a time', () => {
        expect(toDayKey(toUtcDay('2026-12-31') + DAY_MS)).toBe('2027-01-01');
    });

    it('counts a same-day event as one day', () => {
        expect(daySpan('2026-10-13 09:00:00', '2026-10-13 23:00:00')).toBe(1);
    });

    it('counts both ends of a multi-day span', () => {
        expect(daySpan('2026-10-13 22:00:00', '2026-10-15 01:00:00')).toBe(3);
    });

    it('spans a leap day correctly', () => {
        expect(daySpan('2028-02-28', '2028-03-01')).toBe(3);
    });
});

describe('icsStamp', () => {
    it('formats a stored UTC datetime as a calendar timestamp', () => {
        expect(icsStamp('2026-10-13 11:30:00')).toBe('20261013T113000Z');
    });

    it('drops the milliseconds of an ISO string', () => {
        expect(icsStamp('2026-10-13T11:30:00.000Z')).toBe('20261013T113000Z');
    });
});

describe('buildIcs', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('wraps one event in a calendar with CRLF line endings', () => {
        const lines = buildIcs(event()).split('\r\n');

        expect(lines[0]).toBe('BEGIN:VCALENDAR');
        expect(lines).toContain('BEGIN:VEVENT');
        expect(lines.at(-1)).toBe('END:VCALENDAR');
        expect(buildIcs(event())).not.toMatch(/[^\r]\n/);
    });

    it('writes the start and end as UTC timestamps', () => {
        const text = buildIcs(event());

        expect(property(text, 'DTSTART')).toBe('DTSTART:20261013T113000Z');
        expect(property(text, 'DTEND')).toBe('DTEND:20261013T130000Z');
    });

    it('keeps the UID stable across downloads so a re-import updates the event', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-01T08:00:00Z'));
        const first = buildIcs(event());

        vi.setSystemTime(new Date('2026-10-05T08:00:00Z'));
        const second = buildIcs(event());

        expect(property(first, 'UID')).toBe(`UID:fluent-event-42@${window.location.host}`);
        expect(property(second, 'UID')).toBe(property(first, 'UID'));
        expect(property(first, 'DTSTAMP')).toBe('DTSTAMP:20261001T080000Z');
        expect(property(second, 'DTSTAMP')).toBe('DTSTAMP:20261005T080000Z');
    });

    it('escapes the characters that carry meaning in a calendar line', () => {
        const text = buildIcs(event({ title: 'Talks, food; drinks \\ more' }));

        expect(property(text, 'SUMMARY')).toBe('SUMMARY:Talks\\, food\\; drinks \\\\ more');
    });

    it('turns line breaks into literal \\n so the description stays one property', () => {
        const text = buildIcs(event({ description: 'Line one\r\nLine two\nLine three' }));

        expect(property(text, 'DESCRIPTION')).toBe('DESCRIPTION:Line one\\nLine two\\nLine three');
    });

    it('writes an empty value rather than "undefined" for missing text', () => {
        const text = buildIcs(event({ description: undefined, location: null }));

        expect(property(text, 'DESCRIPTION')).toBe('DESCRIPTION:');
        expect(property(text, 'LOCATION')).toBe('LOCATION:');
    });

    it('folds long lines to 75 bytes and unfolds back to the original', () => {
        const description = 'A long agenda item. '.repeat(20).trim();
        const text = buildIcs(event({ description }));

        text.split('\r\n').forEach(line => expect(bytes(line)).toBeLessThanOrEqual(75));
        expect(property(text, 'DESCRIPTION')).toBe(`DESCRIPTION:${description}`);
    });

    it('folds multi-byte text between whole characters', () => {
        // 2-, 3- and 4-byte characters, so a byte-count fold would split one in half.
        const description = 'Café — 日本語 🎉 '.repeat(12).trim();
        const text = buildIcs(event({ description }));
        const physical = text.split('\r\n');

        physical.forEach(line => expect(bytes(line)).toBeLessThanOrEqual(75));
        expect(physical.filter(line => line.startsWith(' ')).length).toBeGreaterThan(1);
        expect(property(text, 'DESCRIPTION')).toBe(`DESCRIPTION:${description}`);
    });
});

describe('downloadIcs', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('downloads the text as a named .ics file and releases the object URL', async () => {
        const create = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:fake-ics');
        const revoke = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
        let clicked = null;
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function () {
            clicked = this;
        });

        downloadIcs('BEGIN:VCALENDAR', 'meetup.ics');

        const blob = create.mock.calls[0][0];
        expect(blob.type).toBe('text/calendar;charset=utf-8');
        expect(await blob.text()).toBe('BEGIN:VCALENDAR');
        expect(clicked.download).toBe('meetup.ics');
        expect(clicked.href).toBe('blob:fake-ics');
        expect(revoke).toHaveBeenCalledWith('blob:fake-ics');
    });
});
