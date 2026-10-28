import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import EventActions from '../../../resources/fcom-portal/EventActions';

const rsvp = (overrides = {}) => ({ id: 5, entry_type: 'rsvp', is_past: false, registration_open: true, my_status: null, ...overrides });
const actions = (event = rsvp()) => mount(EventActions, { props: { event }, attachTo: document.body });

const deferredResponse = () => {
    let resolve;
    const promise = new Promise(res => {
        resolve = res;
    });
    return { promise, resolve: (body, status = 200) => resolve(new Response(JSON.stringify(body), { status })) };
};

let wrapper;

beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify({ event: rsvp({ my_status: 'going' }) }))));
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('EventActions', () => {
    it('shows an Ended badge and no RSVP for a past event', () => {
        wrapper = actions(rsvp({ is_past: true }));

        expect(wrapper.find('.fe-event-actions__badge').text()).toBe('Ended');
        expect(wrapper.find('.fe-rsvp').exists()).toBe(false);
    });

    it('says Event full in place of the RSVP for a member without a seat', () => {
        wrapper = actions(rsvp({ is_full: true, my_status: 'not_going' }));

        expect(wrapper.find('.fe-event-actions__state').text()).toBe('Event full');
        expect(wrapper.find('.fe-rsvp').exists()).toBe(false);
    });

    it('says when registration opens in place of the RSVP', () => {
        wrapper = actions(rsvp({ registration_open: false, registration_opens_at: '2026-11-03 10:00:00' }));

        expect(wrapper.find('.fe-event-actions__state').text()).toBe('Opens Tue, Nov 3, 2026 · 10:00 AM');
        expect(wrapper.find('.fe-rsvp').exists()).toBe(false);
    });

    it('says registration closed, but keeps a going member the RSVP to give up the seat', () => {
        wrapper = actions(rsvp({ registration_open: false, is_full: true }));

        expect(wrapper.find('.fe-event-actions__state').text()).toBe('Registration closed');
        wrapper.unmount();

        wrapper = actions(rsvp({ registration_open: false, my_status: 'checked_in' }));

        expect(wrapper.find('.fe-rsvp__label').text()).toBe('Going');
    });

    it.each([
        ['an ended event says Ended, not closed', rsvp({ is_past: true, registration_open: false }), '.fe-event-actions__badge', 'Ended'],
        ['a member who said not going sees it closed', rsvp({ registration_open: false, my_status: 'not_going' }), '.fe-event-actions__state', 'Registration closed'],
        ['a member who said not going sees when it opens', rsvp({ registration_open: false, registration_opens_at: '2026-11-03 10:00:00', my_status: 'not_going' }), '.fe-event-actions__state', 'Opens Tue, Nov 3, 2026 · 10:00 AM'],
        ['a full event with a waitlist says closed, not Join waitlist', rsvp({ registration_open: false, is_full: true, waitlist: true }), '.fe-event-actions__state', 'Registration closed'],
        ['a waitlisted member still sees where they stand', rsvp({ registration_open: false, my_status: 'waitlisted' }), '.fe-event-actions__state', 'Waitlisted']
    ])('outside the registration window, %s', (label, event, selector, text) => {
        wrapper = actions(event);

        expect(wrapper.find(selector).text()).toBe(text);
        expect(wrapper.find('.fe-rsvp').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Join waitlist');
    });

    it('keeps the RSVP for a guest who already holds a seat on a full event', () => {
        wrapper = actions(rsvp({ is_full: true, my_status: 'checked_in' }));

        expect(wrapper.find('.fe-rsvp__label').text()).toBe('Going');
        expect(wrapper.find('.fe-rsvp__trigger').attributes('disabled')).toBeUndefined();
    });

    it('offers a full event with a waitlist as one Join waitlist button', async () => {
        wrapper = actions(rsvp({ is_full: true, waitlist: true, my_status: 'not_going' }));

        expect(wrapper.find('.fe-rsvp').exists()).toBe(false);

        await wrapper.find('.fe-event-actions__join').trigger('click');
        await flushPromises();

        expect(fetch.mock.calls[0][1].body).toBe('{"status":"going"}');
    });

    it.each([
        ['waitlisted', 'Waitlisted'],
        ['cancelled', 'Cancelled'],
    ])('shows a %s member their status as text, with no RSVP to change it', (status, label) => {
        wrapper = actions(rsvp({ is_full: false, waitlist: true, my_status: status }));

        expect(wrapper.find('.fe-event-actions__state').text()).toBe(label);
        expect(wrapper.find('.fe-rsvp').exists()).toBe(false);
        expect(wrapper.find('.fe-event-actions__join').exists()).toBe(false);
    });

    it("sends a logged-out visitor to fcom's login page", () => {
        window.fluentEventFcomPortal.is_logged_in = false;
        wrapper = actions();

        const link = wrapper.find('a');
        expect(link.text()).toBe('Log in to RSVP');
        expect(link.attributes('href')).toBe('https://example.test/portal/?fcom_action=auth');
    });

    it("labels the trigger with the member's answer", () => {
        const label = (status) => mount(EventActions, { props: { event: rsvp({ my_status: status }) } }).find('.fe-rsvp__label').text();

        expect(label('going')).toBe('Going');
        expect(label('checked_in')).toBe('Going');
        expect(label('not_going')).toBe('Not going');
        expect(label(null)).toBe('Interested?');
    });

    it('opens the menu with the current answer checked', async () => {
        wrapper = actions(rsvp({ my_status: 'not_going' }));

        await wrapper.find('.fe-rsvp__trigger').trigger('click');

        const options = wrapper.findAll('[role="menuitemradio"]');
        expect(wrapper.find('.fe-rsvp__trigger').attributes('aria-expanded')).toBe('true');
        expect(options.map(option => option.attributes('aria-checked'))).toEqual(['false', 'true']);
    });

    it('closes on a click anywhere else on the page', async () => {
        wrapper = actions();
        await wrapper.find('.fe-rsvp__trigger').trigger('click');

        document.body.click();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="menu"]').exists()).toBe(false);
    });

    it('closes on Escape and hands focus back to the trigger', async () => {
        wrapper = actions();
        await wrapper.find('.fe-rsvp__trigger').trigger('click');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="menu"]').exists()).toBe(false);
        expect(document.activeElement).toBe(wrapper.find('.fe-rsvp__trigger').element);
    });

    it('sends nothing when the member picks the answer they already gave', async () => {
        wrapper = actions(rsvp({ my_status: 'checked_in' }));
        await wrapper.find('.fe-rsvp__trigger').trigger('click');

        await wrapper.findAll('[role="menuitemradio"]')[0].trigger('click');

        expect(fetch).not.toHaveBeenCalled();
    });

    it('registers a new answer and hands back the updated event', async () => {
        wrapper = actions();
        await wrapper.find('.fe-rsvp__trigger').trigger('click');

        await wrapper.findAll('[role="menuitemradio"]')[0].trigger('click');
        await flushPromises();

        const [url, options] = fetch.mock.calls[0];
        expect(url).toBe('https://example.test/wp-json/fluent-event/v2/community/events/5/register');
        expect(options.body).toBe('{"status":"going"}');
        expect(wrapper.emitted('error')).toEqual([['']]);
        expect(wrapper.emitted('done')[0][0].event.my_status).toBe('going');
    });

    it('disables the trigger while the answer is saving', async () => {
        const response = deferredResponse();
        fetch.mockReturnValueOnce(response.promise);
        wrapper = actions();
        await wrapper.find('.fe-rsvp__trigger').trigger('click');

        await wrapper.findAll('[role="menuitemradio"]')[1].trigger('click');
        expect(wrapper.find('.fe-rsvp__trigger').attributes('disabled')).toBeDefined();

        response.resolve({ event: rsvp({ my_status: 'not_going' }) });
        await flushPromises();
        expect(wrapper.find('.fe-rsvp__trigger').attributes('disabled')).toBeUndefined();
    });

    it("reports the server's reason when the answer is refused", async () => {
        fetch.mockResolvedValueOnce(new Response(JSON.stringify({ message: 'This event has ended.' }), { status: 422 }));
        wrapper = actions();
        await wrapper.find('.fe-rsvp__trigger').trigger('click');

        await wrapper.findAll('[role="menuitemradio"]')[0].trigger('click');
        await flushPromises();

        expect(wrapper.emitted('error').at(-1)).toEqual(['This event has ended.']);
        expect(wrapper.emitted('done')).toBeUndefined();
    });

    it('stops listening to the page once it is gone', async () => {
        const remove = vi.spyOn(document, 'removeEventListener');
        wrapper = actions();
        await wrapper.find('.fe-rsvp__trigger').trigger('click');
        const { onOutsideClick, onKeydown } = wrapper.vm;

        wrapper.unmount();
        wrapper = null;

        expect(remove).toHaveBeenCalledWith('click', onOutsideClick);
        expect(remove).toHaveBeenCalledWith('keydown', onKeydown);
    });
});
