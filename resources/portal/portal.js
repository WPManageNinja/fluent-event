// The landing pages' script. On the events list, a calendar chip swaps the list in place. On an event
// page, everyone registers as a visitor, by name and email; the server draws every state into the side
// card, and this posts the form and swaps in the card it gets back.

const card = () => document.querySelector('[data-fe-card]');

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
async function send(url, body) {
    const { nonce, eventNonce, error } = card().dataset;
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

// The card is this plugin's own endpoint render, escaped in event-side.php like the page itself.
function swap(html) {
    card().outerHTML = html;

    const focus = card().querySelector('[data-fe-focus]');
    focus && focus.focus();
}

export async function submitForm(form) {
    const button = form.querySelector('[type="submit"]');
    const message = form.querySelector('.fe-register__error');

    button.disabled = true;
    message.textContent = '';

    try {
        swap((await send(form.action, Object.fromEntries(new FormData(form)))).html);
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
function revealActiveChip() {
    const chip = document.querySelector('[data-fe-chip][aria-current]');
    chip && chip.scrollIntoView({ block: 'nearest', inline: 'center' });
}

// Only the latest pick may land: a slower answer to an earlier chip would otherwise overwrite it.
let loading = null;

// The server renders the list for the chip's link, as for a normal load; this takes its [data-fe-events]
// and moves the address bar there, so the link stays shareable and Back returns to the previous calendar.
export async function showEvents(url, push = true, focus = '[data-fe-chip][aria-current]') {
    const list = document.querySelector('[data-fe-events]');
    const results = list.querySelector('[data-fe-results]');
    const skeleton = list.querySelector('template[data-fe-skeleton]');
    let page;
    let next;

    // The results go grey in the current view's shape until the new list is in.
    results.setAttribute('aria-busy', 'true');
    skeleton && results.replaceChildren(skeleton.content.cloneNode(true));

    loading && loading.abort();
    loading = new AbortController();

    try {
        const response = await fetch(url, { credentials: 'same-origin', signal: loading.signal });
        page = new DOMParser().parseFromString(await response.text(), 'text/html');
        next = page.querySelector('[data-fe-events]');

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
    fitChips();

    if (push) {
        history.pushState(null, '', url);

        // The clicked link was replaced with the list; keep keyboard focus on its successor.
        const link = next.querySelector(focus);
        link && link.focus({ preventScroll: true });
    }

    revealActiveChip();
}

document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-fe-chip], [data-fe-nav]');

    // A modified click opens the link the way the browser normally would.
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();

    // The calendar's Month / Week / Day and ‹ Today › links.
    if (link.dataset.feNav) {
        showEvents(link.href, true, `[data-fe-nav="${link.dataset.feNav}"]`);
        return;
    }

    // Selected at once, before the list it filters to arrives; a pick from the "More" panel closes it.
    link.closest('[data-fe-chips]').querySelectorAll('[data-fe-chip]').forEach((other) => {
        other.classList.toggle('fe-calendar-chips__chip--active', other === link);
        other.toggleAttribute('aria-current', other === link);
    });
    link.setAttribute('aria-current', 'page');
    link.closest('[popover]') && link.closest('[popover]').hidePopover();

    showEvents(link.href);
});

window.addEventListener('popstate', () => {
    if (document.querySelector('[data-fe-events]')) {
        showEvents(location.href, false);
    }
});

let fitting = 0;

window.addEventListener('resize', () => {
    cancelAnimationFrame(fitting);
    fitting = requestAnimationFrame(() => fitChips());
});

fitChips();
revealActiveChip();
