// Shared by the event card (which runs on FluentCommunity's Vue) and the lazy page app.

export const portalVars = () => window.fluentEventFcomPortal || {};

export function t(text, ...args) {
    let translated = (portalVars().i18n || {})[text] || text;
    let index = 0;

    return translated.replace(/%(\d+\$)?[sd]/g, (match, position) => {
        const value = position ? args[parseInt(position, 10) - 1] : args[index++];
        return value === undefined ? match : value;
    });
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
};

export const communityApi = {
    post: (route, data) => request(window.fluentComAdmin.rest.url, 'POST', route, data),
};

export function errorMessage(error) {
    if (error && error.message) {
        return error.message;
    }

    const errors = (error && error.errors) || {};

    for (const field of Object.keys(errors)) {
        const first = Object.values(errors[field] || {})[0];

        if (first) {
            return first;
        }
    }

    return t('Something went wrong. Please try again.');
}

const dayjs = (value) => window.dayjs(value);

export function eventDateRange(event) {
    const start = dayjs(event.start_at_local);
    const end = dayjs(event.end_at_local);
    const time = 'h:mm A';

    if (start.isSame(end, 'day')) {
        return `${start.format('ddd, MMM D, YYYY')} · ${start.format(time)} – ${end.format(time)}`;
    }

    return `${start.format('MMM D, YYYY ' + time)} – ${end.format('MMM D, YYYY ' + time)}`;
}

export function eventMonth(event) {
    return dayjs(event.start_at_local).format('MMM');
}

export function eventDay(event) {
    return dayjs(event.start_at_local).format('D');
}

export function timezoneOffset(zone) {
    try {
        const parts = new Intl.DateTimeFormat('en-US', { timeZone: zone, timeZoneName: 'shortOffset' }).formatToParts(new Date());
        return (parts.find((part) => part.type === 'timeZoneName') || {}).value || '';
    } catch (e) {
        return '';
    }
}

export function isSeated(event) {
    return ['going', 'checked_in'].includes(event.my_status);
}

// A member's own open registration is cancelled from the ⋮ menu, not from the action row.
export function canCancelRegistration(event) {
    return !event.is_past && event.entry_type === 'open' && isSeated(event) && !!portalVars().is_logged_in;
}

export function hasEventActions(event) {
    return event.is_past || event.entry_type !== 'none';
}

export function guestCountLabel(event) {
    if (event.entry_type === 'none' || event.guest_count === null || event.guest_count === undefined) {
        return '';
    }

    return t('%s going', event.guest_count);
}
