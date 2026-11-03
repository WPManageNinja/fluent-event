import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import EventActions from '../../../resources/fcom-portal/EventActions';

const rsvp = (overrides = {}) => ({ id: 5, entry_type: 'rsvp', is_past: false, my_status: null, ...overrides });
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

    it("opens a member's Register form filled in, email locked, and registers on submit", async () => {
        window.fluentEventFcomPortal.user = { name: 'Rafi', email: 'rafi@example.test' };
        wrapper = actions(rsvp({ entry_type: 'open' }));

        await wrapper.find('button').trigger('click');
        expect(fetch).not.toHaveBeenCalled();

        const email = wrapper.find('#fe-guest-email-5');
        expect(wrapper.find('#fe-guest-name-5').element.value).toBe('Rafi');
        expect(email.element.value).toBe('rafi@example.test');
        expect(email.attributes('disabled')).toBeDefined();
        expect(wrapper.find('.fe-guest-register__trap').exists()).toBe(false);

        await wrapper.find('#fe-guest-name-5').setValue('Rafi Ahmed');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        const [url, options] = fetch.mock.calls[0];
        expect(url).toBe('https://example.test/wp-json/fluent-event/v2/community/events/5/register');
        expect(JSON.parse(options.body)).toEqual({ status: 'going', name: 'Rafi Ahmed' });
        expect(wrapper.emitted('done')).toHaveLength(1);
    });

    it('registers a visitor for an open event by name and email', async () => {
        window.fluentEventFcomPortal.is_logged_in = false;
        wrapper = actions(rsvp({ entry_type: 'open', register_nonce: 'n0nce' }));

        await wrapper.find('button').trigger('click');
        await wrapper.vm.$nextTick();
        expect(document.activeElement).toBe(wrapper.find('#fe-guest-name-5').element);

        await wrapper.find('#fe-guest-name-5').setValue('Rafi');
        await wrapper.find('#fe-guest-email-5').setValue('rafi@example.test');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        const [url, options] = fetch.mock.calls[0];
        expect(url).toBe('https://example.test/wp-json/fluent-event/v2/community/events/5/register-guest');
        expect(JSON.parse(options.body)).toEqual({ name: 'Rafi', email: 'rafi@example.test', website: '', nonce: 'n0nce' });
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.emitted('done')).toHaveLength(1);
    });

    it("keeps a visitor's form open with the server's reason when refused", async () => {
        window.fluentEventFcomPortal.is_logged_in = false;
        fetch.mockResolvedValueOnce(new Response(JSON.stringify({ message: 'A guest with this email is already on this event.' }), { status: 422 }));
        wrapper = actions(rsvp({ entry_type: 'open' }));

        await wrapper.find('button').trigger('click');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('.fe-guest-register__error').text()).toBe('A guest with this email is already on this event.');
        expect(wrapper.emitted('done')).toBeUndefined();
    });

    it("marks an open event's guest registered, with nothing to undo", () => {
        for (const status of ['going', 'checked_in']) {
            const registered = mount(EventActions, { props: { event: rsvp({ entry_type: 'open', my_status: status }) } });

            expect(registered.find('.fe-event-actions__badge').text()).toBe('Registered');
            expect(registered.find('button').exists()).toBe(false);
        }
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
