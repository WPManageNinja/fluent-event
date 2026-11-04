// Registration on the public event page. Everyone registers as a visitor, by name and email; the
// server draws every state into the side card, and this posts the form and swaps in the card it gets back.

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
