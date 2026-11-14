import dayjs from 'dayjs';
import updateLocale from 'dayjs/plugin/updateLocale';
import advancedFormat from 'dayjs/plugin/advancedFormat';
import isoWeek from 'dayjs/plugin/isoWeek';

dayjs.extend(updateLocale);
dayjs.extend(advancedFormat);
// advancedFormat's week tokens ("W", "GGGG") call isoWeek() and throw without it.
dayjs.extend(isoWeek);

/** One day in milliseconds. */
export const DAY_MS = 86400000;

export const toUtcDay = (value) => Date.parse(`${value.slice(0, 10)}T00:00:00Z`);

export const toDayKey = (time) => new Date(time).toISOString().slice(0, 10);

/**
 * The date an event ends on, 'YYYY-MM-DD': one ending at exactly midnight ends the day before,
 * as calendars show it (22:00 → 00:00 is one evening, not two days).
 * @param  {string} start
 * @param  {string} end
 * @returns {string}
 */
export const lastDay = (start, end) => (end > start && /[ T]00:00(:00)?$/.test(end)
    ? toDayKey(toUtcDay(end) - DAY_MS)
    : end.slice(0, 10));

/**
 * Inclusive day span between two bounds; 1 when both fall on the same date.
 * @param  {string} start
 * @param  {string} end
 * @returns {number}
 */
export const daySpan = (start, end) => (toUtcDay(lastDay(start, end)) - toUtcDay(start)) / DAY_MS + 1;

// WordPress "Week Starts On" for our dayjs (Element Plus reads it) and, in the portal, fcom's window.dayjs.
export function applyWeekStart(value, hostDayjs) {
    const day = parseInt(value, 10);
    const weekStart = day >= 0 && day <= 6 ? day : 0;

    dayjs.updateLocale('en', { weekStart });
    hostDayjs?.updateLocale('en', { weekStart });
}

// Element Plus writes a picked date with our dayjs, which only knows English. Lend it the host's language
// (fcom's dayjs has the site's), keeping the week start above; returns the name Element Plus should use.
export function borrowLocale(hostDayjs) {
    const name = hostDayjs.locale();
    const locale = hostDayjs.Ls[name];

    if (!locale) {
        return 'en';
    }

    if (name !== 'en') {
        dayjs.locale({ ...locale, weekStart: dayjs.Ls.en.weekStart }, null, true);
    }

    return name;
}

// "Add to calendar" files (.ics).

const encoder = new TextEncoder();

// Commas, semicolons, backslashes and newlines carry meaning in a calendar line.
const icsText = (value) => String(value || '').replace(/\\/g, '\\\\').replace(/([,;])/g, '\\$1').replace(/\r?\n/g, '\\n');

// Lines are capped at 75 bytes, not characters; fold between whole characters so accents, CJK and emoji survive.
const icsFold = (line) => {
    const lines = [];
    let current = '';
    let bytes = 0;

    for (const char of line) {
        const size = encoder.encode(char).length;

        // A continuation line starts with a space, which counts toward its 75.
        if (bytes + size > (lines.length ? 74 : 75)) {
            lines.push(current);
            current = '';
            bytes = 0;
        }

        current += char;
        bytes += size;
    }

    lines.push(current);

    return lines.join('\r\n ');
};

/**
 * A UTC time as calendars write it, e.g. 20261013T113000Z.
 * @param  {string} value  Stored "YYYY-MM-DD HH:mm:ss" in UTC, or an ISO string.
 * @returns {string}
 */
export const icsStamp = (value) => `${String(value).replace(/[-:]/g, '').replace(' ', 'T').slice(0, 15)}Z`;

/**
 * One event as calendar file text. The UID is stable per event, so a re-import updates it instead of duplicating.
 * @param  {{ id: number, title: string, start_at: string, end_at: string, description: string, location: string, url: string }} event
 * @returns {string}
 */
export function buildIcs(event) {
    const lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Fluent Events//EN',
        'CALSCALE:GREGORIAN',
        'BEGIN:VEVENT',
        `UID:fluent-event-${event.id}@${window.location.host}`,
        `DTSTAMP:${icsStamp(new Date().toISOString())}`,
        `DTSTART:${icsStamp(event.start_at)}`,
        `DTEND:${icsStamp(event.end_at)}`,
        `SUMMARY:${icsText(event.title)}`,
        `DESCRIPTION:${icsText(event.description)}`,
        `LOCATION:${icsText(event.location)}`,
        `URL:${event.url}`,
        'END:VEVENT',
        'END:VCALENDAR',
    ];

    return lines.map(icsFold).join('\r\n');
}

export function downloadIcs(text, filename) {
    const blob = new Blob([text], { type: 'text/calendar;charset=utf-8' });
    const link = document.createElement('a');

    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
    URL.revokeObjectURL(link.href);
}
