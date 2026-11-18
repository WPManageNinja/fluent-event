import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { errorMessage, submitForm, showEvents, fitChips, startCountdowns } from '../../../resources/portal/portal';

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

    it('opens the confirmation the new card carries', async () => {
        const showPopover = vi.fn();
        HTMLElement.prototype.showPopover = showPopover;
        document.body.innerHTML = card(`
            <form data-fe-register action="https://site.test/register-guest">
                <input name="name" value="Ada">
                <input name="email" value="ada@example.test">
                <p class="fe-register__error"></p>
                <button type="submit">Register</button>
            </form>`);
        fetch.mockResolvedValue(respond({ html: card('<div popover data-fe-answer>You\'re registered</div>') }));

        await submitForm(document.querySelector('form'));

        expect(showPopover).toHaveBeenCalledTimes(1);
        expect(showPopover.mock.instances[0]).toBe(document.querySelector('[data-fe-answer]'));
        delete HTMLElement.prototype.showPopover;
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

describe('picking a day in the month grid on a phone', () => {
    beforeEach(() => {
        Element.prototype.scrollIntoView = vi.fn();
        window.matchMedia = vi.fn(() => ({ matches: false }));
        document.body.innerHTML = `
            <section class="fe-month">
                <button data-fe-day="2026-11-24" aria-pressed="false"></button>
                <button data-fe-day="2026-11-25" aria-pressed="false"></button>
                <div data-fe-day-events="2026-11-24" hidden>Single Workshop</div>
                <div data-fe-day-events="2026-11-25" hidden>No events on this day.</div>
            </section>`;
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    const pick = (date) => document.querySelector(`[data-fe-day="${date}"]`).dispatchEvent(new MouseEvent('click', { bubbles: true }));
    const shown = () => [...document.querySelectorAll('[data-fe-day-events]')].filter((panel) => !panel.hidden).map((panel) => panel.textContent);
    const pressed = () => [...document.querySelectorAll('[aria-pressed="true"]')].map((day) => day.dataset.feDay);

    it('shows nothing until a day is picked', () => {
        expect(shown()).toEqual([]);
    });

    it("marks the picked day and shows only its events, or that it has none", () => {
        pick('2026-11-24');
        expect(pressed()).toEqual(['2026-11-24']);
        expect(shown()).toEqual(['Single Workshop']);

        pick('2026-11-25');
        expect(pressed()).toEqual(['2026-11-25']);
        expect(shown()).toEqual(['No events on this day.']);
    });

    // The list sits under the grid, often below the screen on a phone.
    it('scrolls the picked day\'s list into view, only as far as it takes', () => {
        pick('2026-11-24');

        const panel = document.querySelector('[data-fe-day-events="2026-11-24"]');
        expect(Element.prototype.scrollIntoView).toHaveBeenCalledWith({ block: 'nearest', behavior: 'smooth' });
        expect(Element.prototype.scrollIntoView.mock.instances[0]).toBe(panel);
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

// The shortcodes and blocks can put several events and lists on one page; each acts on its own.
describe('several events and lists on one page', () => {
    const eventCard = (name, inner) => `
        <aside data-fe-card data-error="Something went wrong." data-event-nonce="nonce-${name}">${inner}</aside>`;

    const form = (name) => `
        <form data-fe-register action="https://site.test/${name}/register-guest">
            <input name="email" value="ada@example.test">
            <p class="fe-register__error"></p>
            <button type="submit">Register</button>
        </form>`;

    const events = (label) => `
        <div data-fe-events>
            <div data-fe-chips>
                <nav data-fe-chips-row>
                    <a data-fe-chip href="https://site.test/page/?calendar=meetups">Meetups</a>
                    <button data-fe-chips-more data-label="More (%d)" hidden></button>
                </nav>
                <div data-fe-chips-panel popover></div>
            </div>
            <template data-fe-skeleton><span class="fe-skeleton"></span></template>
            <div data-fe-results><p class="events">${label}</p></div>
        </div>`;

    beforeEach(() => {
        vi.stubGlobal('fetch', vi.fn());
        Element.prototype.scrollIntoView = vi.fn();
        window.matchMedia = vi.fn(() => ({ matches: false }));
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.body.innerHTML = '';
    });

    it("registers on the second event with that event's own nonce and swaps only its card", async () => {
        document.body.innerHTML = eventCard('a', form('a')) + eventCard('b', form('b'));
        fetch.mockResolvedValue(respond({ html: eventCard('b', '<span class="fe-event-page__badge" tabindex="-1" data-fe-focus>Going</span>') }));

        await submitForm(document.querySelectorAll('form')[1]);

        expect(fetch.mock.calls[0][0]).toBe('https://site.test/b/register-guest');
        expect(JSON.parse(fetch.mock.calls[0][1].body).nonce).toBe('nonce-b');
        const cards = document.querySelectorAll('[data-fe-card]');
        expect(cards[0].querySelector('form')).not.toBeNull();
        expect(cards[1].textContent).toContain('Going');
    });

    it('swaps only the list whose chip was picked', async () => {
        document.body.innerHTML = events('First list') + events('Second list');
        fetch.mockResolvedValue(new Response(`<html><head><title>Meetups</title></head><body>${events('First, filtered')}${events('Second, filtered')}</body></html>`));
        vi.spyOn(history, 'pushState').mockImplementation(() => {});

        document.querySelectorAll('[data-fe-chip]')[1].dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

        await vi.waitFor(() => expect(document.querySelectorAll('.events')[1].textContent).toBe('Second, filtered'));
        expect(document.querySelectorAll('.events')[0].textContent).toBe('First list');
    });
});

describe('countdown to the start', () => {
    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('ticks down each second and gives way to the "Happening now" badge at 0', () => {
        vi.useFakeTimers();
        vi.setSystemTime(0);
        document.body.innerHTML = `
            <div data-fe-countdown="2" data-live-label="Happening now">
                <span data-unit="days"></span><span data-unit="hours"></span><span data-unit="minutes"></span><span data-unit="seconds"></span>
            </div>`;
        const values = () => [...document.querySelectorAll('[data-unit]')].map((value) => value.textContent);

        startCountdowns();
        expect(values()).toEqual(['00', '00', '00', '02']);

        vi.advanceTimersByTime(1000);
        expect(values()).toEqual(['00', '00', '00', '01']);

        vi.advanceTimersByTime(1000);
        expect(document.querySelector('[data-fe-countdown]')).toBeNull();
        expect(document.querySelector('.fe-live-badge').textContent).toBe('Happening now');
    });

    // A suspended tab can miss the whole event between two ticks; reaching 0 then must not mean live.
    it('lands on Ended, not live, when the countdown finishes after the event is over', () => {
        vi.useFakeTimers();
        vi.setSystemTime(0);
        document.body.innerHTML = `
            <div data-fe-countdown="2" data-fe-countdown-end="10" data-live-label="Happening now" data-ended-label="Ended">
                <span data-unit="days"></span><span data-unit="hours"></span><span data-unit="minutes"></span><span data-unit="seconds"></span>
            </div>`;

        startCountdowns();
        vi.setSystemTime(15000);
        vi.advanceTimersByTime(1000);

        expect(document.querySelector('.fe-live-badge')).toBeNull();
        expect(document.querySelector('.fe-event-page__status--ended').textContent).toBe('Ended');
    });
});
