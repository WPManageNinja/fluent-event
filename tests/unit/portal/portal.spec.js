import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { errorMessage, submitForm, chooseStatus } from '../../../resources/portal/portal';

const respond = (body, status = 200) => new Response(JSON.stringify(body), { status });

const card = (inner, attrs = '') => `
    <aside data-fe-card data-error="Something went wrong." ${attrs}>
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

    it('posts a visitor form without the REST nonce and swaps in the card it gets back', async () => {
        document.body.innerHTML = card(`
            <form data-fe-register action="https://site.test/register-guest">
                <input name="name" value="Ada">
                <input name="email" value="ada@example.test">
                <input name="nonce" value="abc">
                <p class="fe-register__error"></p>
                <button type="submit">Register</button>
            </form>`);
        fetch.mockResolvedValue(respond({ html: card('<span class="fe-event-page__badge" tabindex="-1" data-fe-focus>Going</span>') }));

        await submitForm(document.querySelector('form'));

        const [url, options] = fetch.mock.calls[0];
        expect(url).toBe('https://site.test/register-guest');
        expect(options.headers['X-WP-Nonce']).toBeUndefined();
        expect(JSON.parse(options.body)).toEqual({ name: 'Ada', email: 'ada@example.test', nonce: 'abc' });
        expect(document.querySelector('.fe-event-page__badge').textContent).toBe('Going');
        expect(document.activeElement).toBe(document.querySelector('.fe-event-page__badge'));
    });

    it("leaves a member's locked email out and sends their REST nonce", async () => {
        document.body.innerHTML = card(`
            <form data-fe-register action="https://site.test/register">
                <input name="name" value="Zane">
                <input name="email" value="zane@example.test" disabled>
                <input name="status" value="going">
                <p class="fe-register__error"></p>
                <button type="submit">Register</button>
            </form>`, 'data-nonce="rest-nonce" data-url="https://site.test/register"');
        fetch.mockResolvedValue(respond({ html: card('') }));

        await submitForm(document.querySelector('form'));

        const [, options] = fetch.mock.calls[0];
        expect(options.headers['X-WP-Nonce']).toBe('rest-nonce');
        expect(JSON.parse(options.body)).toEqual({ name: 'Zane', status: 'going' });
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

    it('posts a member RSVP choice, but not the one already chosen', async () => {
        document.body.innerHTML = card(`
            <div popover>
                <button data-fe-status="going" aria-checked="true">Going</button>
                <button data-fe-status="not_going" aria-checked="false">Not going</button>
            </div>`, 'data-nonce="rest-nonce" data-url="https://site.test/register"');
        document.querySelector('[popover]').hidePopover = vi.fn();
        fetch.mockResolvedValue(respond({ html: card('') }));

        await chooseStatus(document.querySelector('[data-fe-status="going"]'));
        expect(fetch).not.toHaveBeenCalled();

        await chooseStatus(document.querySelector('[data-fe-status="not_going"]'));
        expect(fetch.mock.calls[0][0]).toBe('https://site.test/register');
        expect(JSON.parse(fetch.mock.calls[0][1].body)).toEqual({ status: 'not_going' });
    });
});
