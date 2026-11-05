import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { errorMessage, submitForm, showEvents, fitChips } from '../../../resources/portal/portal';

const respond = (body, status = 200) => new Response(JSON.stringify(body), { status });

const card = (inner, attrs = '') => `
    <aside data-fe-card data-error="Something went wrong." data-event-nonce="event-nonce" ${attrs}>
        ${inner}
        <p class="fe-event-page__error"></p>
    </aside>`;

describe('errorMessage', () => {
    it("prefers the endpoint's message", () => {
        expect(errorMessage({ message: 'This event has already ended.' }, 'fallback')).toBe('This event has already ended.');
    });

    it('reads the first validation error, sent keyed by field at the top level', () => {
        expect(errorMessage({ email: { unique: 'A guest with this email is already on this event.' } }, 'fallback'))
            .toBe('A guest with this email is already on this event.');
    });

    it('falls back when the answer says nothing usable', () => {
        expect(errorMessage({}, 'fallback')).toBe('fallback');
        expect(errorMessage(null, 'fallback')).toBe('fallback');
    });
});

describe('registration', () => {
    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.body.innerHTML = '';
    });

    it("posts a visitor form with the event's nonce, no REST nonce, and swaps in the card it gets back", async () => {
        document.body.innerHTML = card(`
            <form data-fe-register action="https://site.test/register-guest">
                <input name="name" value="Ada">
                <input name="email" value="ada@example.test">
                <p class="fe-register__error"></p>
                <button type="submit">Register</button>
            </form>`);
        fetch.mockResolvedValue(respond({ html: card('<span class="fe-event-page__badge" tabindex="-1" data-fe-focus>Going</span>') }));

        await submitForm(document.querySelector('form'));

        const [url, options] = fetch.mock.calls[0];
        expect(url).toBe('https://site.test/register-guest');
        expect(options.headers['X-WP-Nonce']).toBeUndefined();
        expect(JSON.parse(options.body)).toEqual({ name: 'Ada', email: 'ada@example.test', nonce: 'event-nonce' });
        expect(document.querySelector('.fe-event-page__badge').textContent).toBe('Going');
        expect(document.activeElement).toBe(document.querySelector('.fe-event-page__badge'));
    });

    it('posts a logged-in viewer the same form, with the REST nonce their event nonce needs', async () => {
        document.body.innerHTML = card(`
            <form data-fe-register action="https://site.test/register-guest">
                <input name="name" value="Zane">
                <input name="email" value="zane@example.test">
                <p class="fe-register__error"></p>
                <button type="submit">Register</button>
            </form>`, 'data-nonce="rest-nonce"');
        fetch.mockResolvedValue(respond({ html: card('') }));

        await submitForm(document.querySelector('form'));

        const [url, options] = fetch.mock.calls[0];
        expect(url).toBe('https://site.test/register-guest');
        expect(options.headers['X-WP-Nonce']).toBe('rest-nonce');
        expect(JSON.parse(options.body)).toEqual({ name: 'Zane', email: 'zane@example.test', nonce: 'event-nonce' });
    });

    it('shows a refusal in the form and lets the visitor try again', async () => {
        document.body.innerHTML = card(`
            <form data-fe-register action="https://site.test/register-guest">
                <input name="email" value="ada@example.test">
                <p class="fe-register__error"></p>
                <button type="submit">Register</button>
            </form>`);
        fetch.mockResolvedValue(respond({ email: { unique: 'A guest with this email is already on this event.' } }, 422));

        await submitForm(document.querySelector('form'));

        expect(document.querySelector('.fe-register__error').textContent).toBe('A guest with this email is already on this event.');
        expect(document.querySelector('[type="submit"]').disabled).toBe(false);
    });
});

describe('calendar chips', () => {
    const list = (active, events) => `
        <div data-fe-events>
            <div data-fe-chips>
                <nav data-fe-chips-row>
                    <a data-fe-chip href="https://site.test/calendars/" ${active === 'all' ? 'aria-current="page"' : ''}>All</a>
                    <a data-fe-chip href="https://site.test/calendars/?calendar=meetups" ${active === 'meetups' ? 'aria-current="page"' : ''}>Meetups</a>
                    <button data-fe-chips-more data-label="More (%d)" hidden></button>
                </nav>
                <div data-fe-chips-panel popover></div>
            </div>
            <template data-fe-skeleton><span class="fe-skeleton"></span></template>
            <div data-fe-results><p class="events">${events}</p></div>
        </div>`;

    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn());
        Element.prototype.scrollIntoView = vi.fn();
        // A phone: the row scrolls, nothing is fitted.
        window.matchMedia = vi.fn(() => ({ matches: false }));
        document.body.innerHTML = list('all', 'Every event');
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.body.innerHTML = '';
    });

    it('swaps in the list the server renders for the chip, moves the address bar there and keeps focus on the chip', async () => {
        fetch.mockResolvedValue(new Response(`<html><head><title>Meetups – Site</title></head><body>${list('meetups', 'Meetup events')}</body></html>`));
        const push = vi.spyOn(history, 'pushState').mockImplementation(() => {});

        await showEvents('https://site.test/calendars/?calendar=meetups');

        expect(fetch.mock.calls[0][0]).toBe('https://site.test/calendars/?calendar=meetups');
        expect(document.querySelector('.events').textContent).toBe('Meetup events');
        expect(document.title).toBe('Meetups – Site');
        expect(push).toHaveBeenCalledWith(null, '', 'https://site.test/calendars/?calendar=meetups');
        expect(document.activeElement.textContent).toBe('Meetups');
    });

    it('falls back to a normal load when the answer has no list, so a 404 still shows', async () => {
        const assign = vi.fn();
        vi.stubGlobal('location', { assign });
        fetch.mockResolvedValue(new Response('<html><body><p>Page not found</p></body></html>', { status: 404 }));

        await showEvents('https://site.test/calendars/?calendar=gone');

        expect(assign).toHaveBeenCalledWith('https://site.test/calendars/?calendar=gone');
    });

    it('selects the chip and greys the results at once, before the list arrives', () => {
        fetch.mockReturnValue(new Promise(() => {}));
        const chip = document.querySelectorAll('[data-fe-chip]')[1];

        chip.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

        expect(chip.getAttribute('aria-current')).toBe('page');
        expect(document.querySelector('[data-fe-chip]').hasAttribute('aria-current')).toBe(false);
        expect(document.querySelector('[data-fe-results] .fe-skeleton')).not.toBeNull();
        expect(document.querySelector('.events')).toBeNull();
        expect(document.querySelector('[data-fe-results]').getAttribute('aria-busy')).toBe('true');
    });

    it('drops an earlier pick still loading, so only the latest list lands', async () => {
        fetch.mockImplementation((url, { signal }) => new Promise((resolve, reject) => {
            signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')));
            if (url.includes('meetups')) {
                setTimeout(() => resolve(new Response(`<html><body>${list('all', 'All events')}</body></html>`)), 10);
            }
        }));
        vi.spyOn(history, 'pushState').mockImplementation(() => {});
        const assign = vi.fn();
        vi.stubGlobal('location', { assign });

        const first = showEvents('https://site.test/calendars/?calendar=slow');
        const latest = showEvents('https://site.test/calendars/?calendar=meetups');
        await Promise.all([first, latest]);

        expect(assign).not.toHaveBeenCalled();
        expect(document.querySelector('.events').textContent).toBe('All events');
    });

    it('leaves a modified click to the browser', () => {
        const chip = document.querySelectorAll('[data-fe-chip]')[1];

        chip.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, metaKey: true }));
        expect(fetch).not.toHaveBeenCalled();

        fetch.mockReturnValue(new Promise(() => {}));
        const plain = new MouseEvent('click', { bubbles: true, cancelable: true });
        chip.dispatchEvent(plain);
        expect(plain.defaultPrevented).toBe(true);
        expect(fetch.mock.calls[0][0]).toBe('https://site.test/calendars/?calendar=meetups');
    });
});

describe('calendar navigation', () => {
    const calendar = (title) => `
        <div data-fe-events>
            <div data-fe-chips>
                <nav data-fe-chips-row>
                    <a data-fe-chip href="https://site.test/events/" aria-current="page">All</a>
                    <button data-fe-chips-more data-label="More (%d)" hidden></button>
                </nav>
                <div data-fe-chips-panel popover></div>
            </div>
            <div class="fe-calendar-head">
                <h2>${title}</h2>
                <a data-fe-nav="span-week" href="https://site.test/events/?view=calendar&span=week">Week</a>
                <a data-fe-nav="prev" href="https://site.test/events/?view=calendar&date=2027-02-01">Previous</a>
                <a data-fe-nav="today" href="https://site.test/events/?view=calendar">Today</a>
                <a data-fe-nav="next" href="https://site.test/events/?view=calendar&date=2027-04-01">Next</a>
            </div>
            <template data-fe-skeleton><span class="fe-skeleton"></span></template>
            <div data-fe-results><p class="events">${title}</p></div>
        </div>`;

    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn());
        Element.prototype.scrollIntoView = vi.fn();
        window.matchMedia = vi.fn(() => ({ matches: false }));
        vi.spyOn(history, 'pushState').mockImplementation(() => {});
        document.body.innerHTML = calendar('March 2027');
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
        document.body.innerHTML = '';
    });

    it('swaps in the next month in place, keeps the chip selection and focus on the arrow', async () => {
        fetch.mockResolvedValue(new Response(`<html><body>${calendar('April 2027')}</body></html>`));
        const next = document.querySelector('[data-fe-nav="next"]');
        const click = new MouseEvent('click', { bubbles: true, cancelable: true });

        next.dispatchEvent(click);
        await vi.waitFor(() => expect(document.querySelector('h2').textContent).toBe('April 2027'));

        expect(click.defaultPrevented).toBe(true);
        expect(fetch.mock.calls[0][0]).toBe('https://site.test/events/?view=calendar&date=2027-04-01');
        expect(history.pushState).toHaveBeenCalledWith(null, '', 'https://site.test/events/?view=calendar&date=2027-04-01');
        expect(document.querySelector('[data-fe-chip]').getAttribute('aria-current')).toBe('page');
        expect(document.activeElement.dataset.feNav).toBe('next');
    });

    it('keeps the header up while the skeleton stands in for the grid', () => {
        fetch.mockReturnValue(new Promise(() => {}));

        document.querySelector('[data-fe-nav="today"]').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

        expect(document.querySelector('h2').textContent).toBe('March 2027');
        expect(document.querySelector('[data-fe-results] .fe-skeleton')).not.toBeNull();
        expect(fetch.mock.calls[0][0]).toBe('https://site.test/events/?view=calendar');
    });
});

describe('fitting the chips into one row', () => {
    const row = (selected) => {
        document.body.innerHTML = `
            <div data-fe-chips>
                <nav data-fe-chips-row>
                    ${['All', 'A', 'B', 'C', 'D', 'E'].map((name) => `<a data-fe-chip ${name === selected ? 'aria-current="page"' : ''}>${name}</a>`).join('')}
                    <button data-fe-chips-more data-label="More (%d)" hidden></button>
                </nav>
                <div data-fe-chips-panel popover></div>
            </div>`;

        // No layout here: every chip and the "More" chip is 100px, and the row holds 400px.
        const nav = document.querySelector('[data-fe-chips-row]');
        Object.defineProperty(nav, 'clientWidth', { get: () => 400 });
        Object.defineProperty(nav, 'scrollWidth', { get: () => [...nav.children].filter((child) => !child.hidden).length * 100 });
    };

    const names = (selector) => [...document.querySelectorAll(`${selector} [data-fe-chip]`)].map((chip) => chip.textContent);

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('moves the chips that don\'t fit into the "More" panel, from the end, and counts them', () => {
        window.matchMedia = vi.fn(() => ({ matches: true }));
        row('All');

        fitChips();

        expect(names('[data-fe-chips-row]')).toEqual(['All', 'A', 'B']);
        expect(names('[data-fe-chips-panel]')).toEqual(['C', 'D', 'E']);
        expect(document.querySelector('[data-fe-chips-more]').hidden).toBe(false);
        expect(document.querySelector('[data-fe-chips-more]').textContent).toBe('More (3)');
    });

    it('keeps the selected calendar in the row, and refits from the rendered order', () => {
        window.matchMedia = vi.fn(() => ({ matches: true }));
        row('E');

        fitChips();
        fitChips();

        expect(names('[data-fe-chips-row]')).toEqual(['All', 'A', 'E']);
        expect(names('[data-fe-chips-panel]')).toEqual(['B', 'C', 'D']);
    });

    it('leaves every chip in the row on a phone, where it scrolls instead', () => {
        window.matchMedia = vi.fn(() => ({ matches: false }));
        row('All');

        fitChips();

        expect(names('[data-fe-chips-row]')).toEqual(['All', 'A', 'B', 'C', 'D', 'E']);
        expect(document.querySelector('[data-fe-chips-more]').hidden).toBe(true);
    });
});
