// Shared by the event card (which runs on FluentCommunity's Vue) and the lazy page app.
import { buildIcs, downloadIcs, icsStamp } from '@/utils/date';

export const portalVars = () => window.fluentEventFcomPortal || {};

export function t(text, ...args) {
    let translated = (portalVars().i18n || {})[text] || text;
    let index = 0;

    return translated.replace(/%(\d+\$)?[sd]/g, (match, position) => {
        const value = position ? args[parseInt(position, 10) - 1] : args[index++];
        return value === undefined ? match : value;
    });
}

// Our Element Plus speaks English; its calendar and date picker take day and month names from fcom's dayjs, in the site's language.
export function localizedDateNames(locale, dayjs) {
    const datepicker = { ...locale.el.datepicker, weeks: {}, weeksFull: {}, months: {} };

    ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'].forEach((key, day) => {
        datepicker.weeks[key] = dayjs().day(day).format('ddd');
        datepicker.weeksFull[key] = dayjs().day(day).format('dddd');
    });

    ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'].forEach((key, month) => {
        const date = dayjs().date(1).month(month);
        datepicker.months[key] = date.format('MMM');
        datepicker[`month${month + 1}`] = date.format('MMMM');
    });

    return { ...locale, el: { ...locale.el, datepicker } };
}

// One REST client for the portal: FluentEvent routes and FluentCommunity's own feed routes.
async function request(base, method, route, data) {
    const fcomRest = (window.fluentComAdmin && window.fluentComAdmin.rest) || {};
    const isForm = data instanceof FormData;
    const headers = {};

    // Read the nonce on every call; fcom swaps it in place when it renews.
    if (portalVars().is_logged_in && fcomRest.nonce) {
        headers['X-WP-Nonce'] = fcomRest.nonce;
    }

    let url = `${base.replace(/\/$/, '')}/${route}`;
    const options = { method, headers, credentials: 'same-origin' };

    if (method === 'GET') {
        const query = new URLSearchParams(data || {}).toString();
        url += query ? (url.includes('?') ? '&' : '?') + query : '';
    } else if (isForm) {
        options.body = data;
    } else {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data || {});
    }

    const response = await fetch(url, options);
    const json = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw json;
    }

    return json;
}

export const eventsApi = {
    get: (route, data) => request(portalVars().rest_url, 'GET', route, data),
    post: (route, data) => request(portalVars().rest_url, 'POST', route, data),
    patch: (route, data) => request(portalVars().rest_url, 'PATCH', route, data),
};

// Event covers and description images are media library attachments: the event owns them, not its post.
export async function uploadMedia(file) {
    const data = new FormData();
    data.append('file', file);

    return (await request(portalVars().wp_rest_url, 'POST', 'media', data)).source_url;
}

export const communityApi = {
    get: (route, data) => request(window.fluentComAdmin.rest.url, 'GET', route, data),
    post: (route, data) => request(window.fluentComAdmin.rest.url, 'POST', route, data),
};

export function errorMessage(error) {
    if (error && typeof error.message === 'string') {
        return error.message;
    }

    // fcom's validation errors are keyed by field, and its post field is itself named "message".
    const errors = (error && (error.errors || error)) || {};

    for (const field of Object.keys(errors)) {
        const rules = errors[field];
        const first = rules && typeof rules === 'object' ? Object.values(rules)[0] : '';

        if (typeof first === 'string' && first) {
            return first;
        }
    }

    return t('Something went wrong. Please try again.');
}

const dayjs = (value) => window.dayjs(value);
const utc = (value) => window.dayjs.utc(value);

export function eventPhase(event) {
    const now = Date.now();

    if (event.is_past || now >= utc(event.end_at).valueOf()) {
        return 'ended';
    }

    return now >= utc(event.start_at).valueOf() ? 'live' : 'upcoming';
}

// fcom's time format: the site's WordPress setting as a dayjs pattern, so 24-hour sites read 18:00.
export function timeFormat() {
    return (window.fluentComAdmin && window.fluentComAdmin.time_formatter) || 'h:mm A';
}

// fcom's date format: the site's WordPress setting as a dayjs pattern.
export function dateFormat() {
    return (window.fluentComAdmin && window.fluentComAdmin.date_formatter) || 'MMM D, YYYY';
}

// A *_site value, read on the site's clock like every list.
export function siteDateTime(value) {
    const time = dayjs(value);

    return `${time.format(dateFormat())} · ${time.format(timeFormat())}`;
}

export function eventStart(event) {
    return siteDateTime(event.start_at_site);
}

export function eventMonth(event) {
    return dayjs(event.start_at_site).format('MMM');
}

export function eventDay(event) {
    return dayjs(event.start_at_site).format('D');
}

// Pass the event's date to label the offset it will have then, not today's (daylight saving).
export function timezoneOffset(zone, date = new Date()) {
    try {
        const parts = new Intl.DateTimeFormat('en-US', { timeZone: zone, timeZoneName: 'shortOffset' }).formatToParts(date);
        return (parts.find((part) => part.type === 'timeZoneName') || {}).value || '';
    } catch (e) {
        return '';
    }
}

// The event page passes its description and place; the registration dialog has neither to hand.
export function googleCalendarLink(event, details, location) {
    const params = new URLSearchParams({
        action: 'TEMPLATE',
        text: event.title,
        dates: `${icsStamp(event.start_at)}/${icsStamp(event.end_at)}`,
        details,
        location,
    });

    return `https://calendar.google.com/calendar/render?${params.toString()}`;
}

export function saveIcs(event, details, location) {
    const text = buildIcs({
        id: event.id,
        title: event.title,
        start_at: event.start_at,
        end_at: event.end_at,
        description: details,
        location,
        url: window.location.href,
    });

    downloadIcs(text, `${event.slug}.ics`);
}

export function isSeated(event) {
    return ['going', 'checked_in'].includes(event.my_status);
}

// RSVP and open events take registrations here; none takes nobody.
export function hasEventActions(event) {
    return event.is_past || ['rsvp', 'open'].includes(event.entry_type);
}

export function guestCountLabel(event) {
    if (event.guest_count === null || event.guest_count === undefined) {
        return '';
    }

    if (event.capacity) {
        return t('%1$s out of %2$s going', event.guest_count, event.capacity);
    }

    return t('%s going', event.guest_count);
}

// fcom titles its own pages "<title> - <site>"; ours would keep the previous page's tab title.
export function setPageTitle(title) {
    document.title = [title, (window.fluentComAdmin || {}).site_title].filter(Boolean).join(' - ');
}
