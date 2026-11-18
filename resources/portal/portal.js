// The landing pages' script. On the events list, a calendar chip swaps the list in place. On an event
// page, everyone registers as a visitor, by name and email; the server draws every state into the side
// card, and this posts the form and swaps in the card it gets back. A page can hold several events and lists
// (shortcodes, blocks), so each step acts on the card or list it was started from.

import { padPart, startCountdown } from '@/utils/countdown';

// An event page's countdown to its start; at 0 the "Happening now" badge takes its place, or Ended when a
// tab that slept through the whole event wakes past its end.
export function startCountdowns(root = document) {
    root.querySelectorAll('[data-fe-countdown]').forEach((countdown) => {
        const values = countdown.querySelectorAll('[data-unit]');
        let stop = () => {};

        stop = startCountdown(countdown.dataset.feCountdown * 1000, (parts) => {
            // A registration swaps the card, and its countdown with it.
            if (!countdown.isConnected) {
                return stop();
            }

            values.forEach((value) => {
                value.textContent = padPart(parts[value.dataset.unit]);
            });

            if (parts.done) {
                const ended = Date.now() >= countdown.dataset.feCountdownEnd * 1000;
                const status = document.createElement('span');
                status.className = ended ? 'fe-event-page__status fe-event-page__status--ended' : 'fe-live-badge';
                status.textContent = ended ? countdown.dataset.endedLabel : countdown.dataset.liveLabel;
                countdown.replaceWith(status);
            }
        });
    });
}

// The endpoint's own message, else the first validation error: the framework sends those keyed by
// field then rule, at the top level.
export function errorMessage(data, fallback) {
    if (data && typeof data.message === 'string' && data.message) {
        return data.message;
    }

    for (const rules of Object.values((data && (data.errors || data)) || {})) {
        const first = rules && typeof rules === 'object' ? Object.values(rules)[0] : '';

        if (typeof first === 'string' && first) {
            return first;
        }
    }

    return fallback;
}

// Every post carries the event's own nonce; a logged-in viewer's also carries the REST cookie nonce
// WordPress needs to verify it, since it binds the event's nonce to their session.
async function send(card, url, body) {
    const { nonce, eventNonce, error } = card.dataset;
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', ...(nonce ? { 'X-WP-Nonce': nonce } : {}) },
        body: JSON.stringify({ ...body, nonce: eventNonce }),
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok || !data.html) {
        throw new Error(errorMessage(data, error));
    }

    return data;
}

// The card is this plugin's own endpoint render, escaped in event-side.php like the page itself. The new one
// is found again at the old one's place among the page's cards.
function swap(card, html) {
    const index = [...document.querySelectorAll('[data-fe-card]')].indexOf(card);
    card.outerHTML = html;

    const next = document.querySelectorAll('[data-fe-card]')[index];
    const focus = next.querySelector('[data-fe-focus]');
    focus && focus.focus();
    startCountdowns(next);

    return next;
}

export async function submitForm(form) {
    const card = form.closest('[data-fe-card]');
    const button = form.querySelector('[type="submit"]');
    const message = form.querySelector('.fe-register__error');

    button.disabled = true;
    message.textContent = '';

    try {
        const next = swap(card, (await send(card, form.action, Object.fromEntries(new FormData(form)))).html);

        // The answer to a registration carries its confirmation, which opens over the new card.
        const done = next.querySelector('[data-fe-registered]');
        done && done.showPopover();
    } catch (error) {
        message.textContent = error.message;
        button.disabled = false;
    }
}

document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-fe-register]');

    if (form) {
        event.preventDefault();
        submitForm(form);
    }
});

// On wide screens the chips keep to one row: those that don't fit move into the "More" panel, from the
// end, and "All" and the selected chip always stay. Phones scroll the row instead; without JS every chip wraps.
export function fitChips(root = document.querySelector('[data-fe-chips]')) {
    if (!root) {
        return;
    }

    const row = root.querySelector('[data-fe-chips-row]');
    const more = root.querySelector('[data-fe-chips-more]');
    const panel = root.querySelector('[data-fe-chips-panel]');
    // The order they were rendered in, kept on the element so every refit starts from it.
    root.chips = root.chips || [...root.querySelectorAll('[data-fe-chip]')];

    row.replaceChildren(...root.chips, more);
    panel.replaceChildren();
    more.hidden = true;

    const wide = window.matchMedia('(min-width: 768px)').matches;
    root.classList.toggle('fe-calendar-chips--fitted', wide);

    if (!wide || row.scrollWidth <= row.clientWidth) {
        return;
    }

    // Measured at its widest label, so the count it ends on can't push the row over again.
    more.hidden = false;
    more.textContent = more.dataset.label.replace('%d', root.chips.length);

    const moved = [];

    for (let i = root.chips.length - 1; i > 0 && row.scrollWidth > row.clientWidth; i--) {
        if (!root.chips[i].hasAttribute('aria-current')) {
            moved.unshift(root.chips[i]);
            root.chips[i].remove();
        }
    }

    more.textContent = more.dataset.label.replace('%d', moved.length);
    panel.replaceChildren(...moved);
}

// On a phone the chips scroll sideways; bring the selected one into view.
function revealActiveChip(root = document) {
    const chip = root.querySelector('[data-fe-chip][aria-current]');
    chip && chip.scrollIntoView({ block: 'nearest', inline: 'center' });
}

// Only each list's latest pick may land: a slower answer to an earlier chip would otherwise overwrite it.
const loading = [];

// The server renders the list for the chip's link, as for a normal load; this takes its [data-fe-events]
// and moves the address bar there, so the link stays shareable and Back returns to the previous calendar.
// The page renders its lists in the same order every time, so the answer's list sits at this one's place.
export async function showEvents(url, push = true, focus = '[data-fe-chip][aria-current]', list = document.querySelector('[data-fe-events]')) {
    const index = [...document.querySelectorAll('[data-fe-events]')].indexOf(list);
    const results = list.querySelector('[data-fe-results]');
    const skeleton = list.querySelector('template[data-fe-skeleton]');
    let page;
    let next;

    // The results go grey in the current view's shape until the new list is in.
    results.setAttribute('aria-busy', 'true');
    skeleton && results.replaceChildren(skeleton.content.cloneNode(true));

    loading[index] && loading[index].abort();
    loading[index] = new AbortController();

    try {
        const response = await fetch(url, { credentials: 'same-origin', signal: loading[index].signal });
        page = new DOMParser().parseFromString(await response.text(), 'text/html');
        next = page.querySelectorAll('[data-fe-events]')[index];

        if (!response.ok || !next) {
            throw new Error(response.statusText);
        }
    } catch (error) {
        // Superseded by a later pick, which is loading now.
        if (error.name === 'AbortError') {
            return;
        }

        // A normal load shows whatever the server answers, the 404 page included.
        location.assign(url);
        return;
    }

    list.replaceWith(next);
    document.title = page.title;
    fitChips(next.querySelector('[data-fe-chips]'));

    if (push) {
        history.pushState(null, '', url);

        // The clicked link was replaced with the list; keep keyboard focus on its successor.
        const link = next.querySelector(focus);
        link && link.focus({ preventScroll: true });
    }

    revealActiveChip(next);
}

document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-fe-chip], [data-fe-nav]');

    // A modified click opens the link the way the browser normally would.
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();

    const list = link.closest('[data-fe-events]');

    // The calendar's Month / Week / Day and ‹ Today › links.
    if (link.dataset.feNav) {
        showEvents(link.href, true, `[data-fe-nav="${link.dataset.feNav}"]`, list);
        return;
    }

    // Selected at once, before the list it filters to arrives; a pick from the "More" panel closes it.
    link.closest('[data-fe-chips]').querySelectorAll('[data-fe-chip]').forEach((other) => {
        other.classList.toggle('fe-calendar-chips__chip--active', other === link);
        other.toggleAttribute('aria-current', other === link);
    });
    link.setAttribute('aria-current', 'page');
    link.closest('[popover]') && link.closest('[popover]').hidePopover();

    showEvents(link.href, true, undefined, list);
});

window.addEventListener('popstate', () => {
    document.querySelectorAll('[data-fe-events]').forEach((list) => showEvents(location.href, false, undefined, list));
});

// On a phone the month grid shows bars only; a tapped day is marked and its events (or "none") show under the grid.
document.addEventListener('click', (event) => {
    const day = event.target.closest('[data-fe-day]');

    if (!day) {
        return;
    }

    const month = day.closest('.fe-month');

    month.querySelectorAll('[data-fe-day]').forEach((other) => other.setAttribute('aria-pressed', other === day ? 'true' : 'false'));
    month.querySelectorAll('[data-fe-day-events]').forEach((panel) => {
        panel.hidden = panel.dataset.feDayEvents !== day.dataset.feDay;
    });

    // The list opens under the grid, often below the screen: bring it in, moving no further than that.
    const shown = month.querySelector(`[data-fe-day-events="${day.dataset.feDay}"]`);
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    shown && shown.scrollIntoView({ block: 'nearest', behavior: still ? 'auto' : 'smooth' });
});

let fitting = 0;

window.addEventListener('resize', () => {
    cancelAnimationFrame(fitting);
    fitting = requestAnimationFrame(() => document.querySelectorAll('[data-fe-chips]').forEach((root) => fitChips(root)));
});

document.querySelectorAll('[data-fe-chips]').forEach((root) => fitChips(root));
revealActiveChip();
startCountdowns();
