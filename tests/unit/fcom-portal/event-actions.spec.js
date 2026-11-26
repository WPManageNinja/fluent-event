import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { ElMessageBox, ElNotification } from 'element-plus';
import EventActions from '../../../resources/fcom-portal/EventActions';
// Loaded up front: its first transform outlasts flushPromises() when the component imports it mid-test.
import '../../../resources/fcom-portal/element';

const notify = vi.hoisted(() => vi.fn());
vi.mock('element-plus', async (importOriginal) => ({
    ...(await importOriginal()),
    ElNotification: notify,
    ElMessageBox: { alert: vi.fn(), confirm: vi.fn() },
}));
// The RSVP toast imports the notification on its own.
vi.mock('element-plus/es/components/notification/index.mjs', () => ({ ElNotification: notify }));

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
    vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify({ message: 'RSVP updated. You\'re going!', status: 'going', online_meeting_url: '', guest_count: 4, spots_left: null, is_full: false }))));
    ElNotification.mockClear();
    ElMessageBox.alert.mockReset().mockResolvedValue();
    ElMessageBox.confirm.mockReset().mockResolvedValue();
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

    it('offers Join waitlist in place of Going in the RSVP menu on a full event that takes one, and joins it from there', async () => {
        fetch.mockResolvedValue(new Response(JSON.stringify({ message: "You're on the waitlist.", status: 'waitlisted', online_meeting_url: '', guest_count: 2, spots_left: 0, is_full: true })));
        wrapper = actions(rsvp({ is_full: true, waitlist: true }));

        expect(wrapper.find('.fe-event-actions__state').exists()).toBe(false);
        expect(wrapper.find('.fe-rsvp__label').text()).toBe('Interested?');
        await wrapper.find('.fe-rsvp__trigger').trigger('click');
        expect(wrapper.findAll('.fe-rsvp__option').map(o => o.text())).toEqual(['Join waitlist', 'Not going']);

        await wrapper.findAll('.fe-rsvp__option')[0].trigger('click');
        await flushPromises();

        expect(fetch.mock.calls[0][1].body).toBe('{"status":"going"}');
        expect(wrapper.emitted('done')[0][0].event.my_status).toBe('waitlisted');
    });

    it("shows an RSVP's waitlist place as Waitlisted beside Not going, which leaves it", async () => {
        fetch.mockResolvedValue(new Response(JSON.stringify({ message: 'You left the waitlist.', status: 'not_going', online_meeting_url: '', guest_count: 2, spots_left: 0, is_full: true })));
        wrapper = actions(rsvp({ is_full: true, waitlist: true, my_status: 'waitlisted' }));

        expect(wrapper.find('.fe-rsvp__label').text()).toBe('Waitlisted');
        await wrapper.find('.fe-rsvp__trigger').trigger('click');
        const options = wrapper.findAll('.fe-rsvp__option');
        expect(options.map(o => o.text())).toEqual(['Waitlisted', 'Not going']);
        expect(options[0].attributes('aria-checked')).toBe('true');

        // Choosing the place already held sends nothing.
        await options[0].trigger('click');
        await flushPromises();
        expect(fetch).not.toHaveBeenCalled();

        await wrapper.find('.fe-rsvp__trigger').trigger('click');
        await wrapper.findAll('.fe-rsvp__option')[1].trigger('click');
        await flushPromises();
        expect(fetch.mock.calls[0][1].body).toBe('{"status":"not_going"}');
    });

    it('lets a waitlisted member leave the waitlist from its menu', async () => {
        fetch.mockResolvedValue(new Response(JSON.stringify({ message: 'You left the waitlist.', status: 'not_going', online_meeting_url: '', guest_count: 2, spots_left: 0, is_full: true })));
        wrapper = actions(rsvp({ entry_type: 'open', is_full: true, waitlist: true, my_status: 'waitlisted' }));

        expect(wrapper.find('.fe-rsvp__label').text()).toBe('On the waitlist');

        await wrapper.find('.fe-rsvp__trigger').trigger('click');
        await wrapper.find('.fe-rsvp__option--danger').trigger('click');
        await flushPromises();

        expect(ElMessageBox.confirm).toHaveBeenCalled();
        expect(fetch.mock.calls[0][1].body).toBe('{"status":"not_going"}');
    });

    it('shows a visitor who joined the waitlist a badge, with no menu', () => {
        window.fluentEventFcomPortal.is_logged_in = false;
        wrapper = actions(rsvp({ entry_type: 'open', is_full: true, waitlist: true, my_status: 'waitlisted' }));

        expect(wrapper.find('.fe-event-actions__badge').text()).toBe('On the waitlist');
        expect(wrapper.find('.fe-rsvp').exists()).toBe(false);
    });

    it('keeps the RSVP for a guest who already holds a seat on a full event', () => {
        wrapper = actions(rsvp({ is_full: true, my_status: 'checked_in' }));

        expect(wrapper.find('.fe-rsvp__label').text()).toBe('Going');
        expect(wrapper.find('.fe-rsvp__trigger').attributes('disabled')).toBeUndefined();
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

        // A form with no undo ends in a dialog, not a toast.
        const [content, , settings] = ElMessageBox.alert.mock.calls[0];
        expect(content.props.class).toBe('fe-registered');
        expect(settings).toMatchObject({ customClass: 'fe-registered-box', confirmButtonText: 'Done', closeOnPressEscape: true });
        expect(ElNotification).not.toHaveBeenCalled();
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

    it("warns a visitor whose email is already registered, and changes nothing", async () => {
        window.fluentEventFcomPortal.is_logged_in = false;
        fetch.mockResolvedValueOnce(new Response(JSON.stringify({ message: 'This email is already registered for this event.', status: null, refusal: 'already_registered' })));
        wrapper = actions(rsvp({ entry_type: 'open', register_nonce: 'n0nce' }));

        await wrapper.find('button').trigger('click');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        const [content, , settings] = ElMessageBox.alert.mock.calls[0];
        const [mark, title] = content.children[0].children;
        expect(mark.props.class).toContain('fe-registered__check--warning');
        expect(title.children).toBe('This email is already registered');
        expect(settings).toMatchObject({ customClass: 'fe-registered-box', confirmButtonText: 'OK', closeOnPressEscape: true });
        expect(wrapper.emitted('done')).toBeUndefined();
        expect(wrapper.find('form').exists()).toBe(false);
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

    it("marks an open event's guest registered, with a menu to say not going for members only", () => {
        for (const status of ['going', 'checked_in']) {
            const registered = mount(EventActions, { props: { event: rsvp({ entry_type: 'open', my_status: status }) } });

            expect(registered.find('.fe-rsvp__trigger .fe-rsvp__label').text()).toBe('Registered');
        }

        // A visitor isn't recognised again after a reload, so their registration stays final.
        window.fluentEventFcomPortal.is_logged_in = false;
        const visitor = mount(EventActions, { props: { event: rsvp({ entry_type: 'open', my_status: 'going' }) } });
        expect(visitor.find('.fe-event-actions__badge').text()).toBe('Registered');
        expect(visitor.find('button').exists()).toBe(false);
    });

    it('says not going only once the member confirms', async () => {
        // The event was full; the reply brings its seats as they stand once this guest left.
        fetch.mockResolvedValue(new Response(JSON.stringify({ message: "You're no longer going.", status: 'not_going', online_meeting_url: '', guest_count: 5, spots_left: 1, is_full: false })));
        wrapper = actions(rsvp({ entry_type: 'open', my_status: 'going', guest_count: 6, spots_left: 0, is_full: true, online_meeting_url: 'https://meet.example.test/x' }));

        const pick = async () => {
            await wrapper.find('.fe-rsvp__trigger').trigger('click');
            expect(wrapper.find('[role="menuitem"]').text()).toBe('Not going');
            await wrapper.find('[role="menuitem"]').trigger('click');
            await flushPromises();
        };

        ElMessageBox.confirm.mockRejectedValueOnce('cancel');
        await pick();
        expect(fetch).not.toHaveBeenCalled();

        await pick();

        expect(ElMessageBox.confirm.mock.calls[1][1]).toBe('Not going anymore?');
        expect(fetch.mock.calls[0][1].body).toBe('{"status":"not_going"}');
        expect(ElNotification).toHaveBeenCalledWith({ type: 'success', message: "You're no longer going." });
        expect(wrapper.emitted('done')[0][0].event).toMatchObject({ my_status: 'not_going', guest_count: 5, spots_left: 1, is_full: false, online_meeting_url: '' });
    });

    it('tells a member an admin cancelled so, and changes nothing', async () => {
        fetch.mockResolvedValue(new Response(JSON.stringify({ message: 'Your registration was cancelled by the host.', status: null, refusal: 'cancelled' })));
        wrapper = actions(rsvp({ my_status: 'cancelled' }));

        await wrapper.find('.fe-rsvp__trigger').trigger('click');
        await wrapper.find('[role="menuitemradio"]').trigger('click');
        await flushPromises();

        const [content] = ElMessageBox.alert.mock.calls[0];
        const [, title, text] = content.children[0].children;
        expect(title.children).toBe('Your registration was cancelled');
        expect(text.children).toBe('Contact the host if you want to join again.');
        expect(wrapper.emitted('done')).toBeUndefined();
        expect(ElNotification).not.toHaveBeenCalled();
    });

    it('tells a checked-in member saying not going that the check-in stands, and changes nothing', async () => {
        fetch.mockResolvedValue(new Response(JSON.stringify({ message: 'You are already checked in for this event.', status: null, refusal: 'checked_in' })));
        wrapper = actions(rsvp({ my_status: 'checked_in' }));

        await wrapper.find('.fe-rsvp__trigger').trigger('click');
        await wrapper.findAll('[role="menuitemradio"]')[1].trigger('click');
        await flushPromises();

        expect(fetch.mock.calls[0][1].body).toBe('{"status":"not_going"}');
        const [content] = ElMessageBox.alert.mock.calls[0];
        const [, title, text] = content.children[0].children;
        expect(title.children).toBe('You are already checked in');
        expect(text.children).toBe('The host has recorded your attendance. Contact them if something changed.');
        expect(wrapper.emitted('done')).toBeUndefined();
    });

    it("sends a logged-out visitor to fcom's login page and back to this page after", () => {
        window.fluentEventFcomPortal.is_logged_in = false;
        wrapper = actions();

        const link = wrapper.find('a');
        expect(link.text()).toBe('Log in to RSVP');
        expect(link.attributes('href')).toBe(`https://example.test/portal/?fcom_action=auth&redirect_to=${encodeURIComponent(window.location.href)}`);
    });

    it('replaces a redirect the login link already carries', () => {
        window.fluentEventFcomPortal.is_logged_in = false;
        window.fluentComAdmin.auth_url = 'https://example.test/login?redirect_to=https%3A%2F%2Fexample.test%2F';
        wrapper = actions();

        expect(new URL(wrapper.find('a').attributes('href')).searchParams.getAll('redirect_to')).toEqual([window.location.href]);
    });

    it('sends the visitor back to the URL they are on when they click, not the one the link first rendered on', async () => {
        window.fluentEventFcomPortal.is_logged_in = false;
        const before = window.location.href;
        wrapper = actions();

        window.history.pushState({}, '', '/portal/?search=meetup');
        const link = wrapper.find('a');
        // Stops jsdom following the link, after the component's own click handler has run.
        link.element.addEventListener('click', (event) => event.preventDefault());
        await link.trigger('click');
        const redirect = new URL(link.element.href).searchParams.get('redirect_to');
        window.history.replaceState({}, '', before);

        expect(redirect).toMatch(/\/portal\/\?search=meetup$/);
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

    // The reply carries the event's seats as they stand now, everyone's answers included.
    it('registers a new answer and hands back the event updated by it', async () => {
        wrapper = actions(rsvp({ guest_count: 3, spots_left: null }));
        await wrapper.find('.fe-rsvp__trigger').trigger('click');

        await wrapper.findAll('[role="menuitemradio"]')[0].trigger('click');
        await flushPromises();

        const [url, options] = fetch.mock.calls[0];
        expect(url).toBe('https://example.test/wp-json/fluent-event/v2/community/events/5/register');
        expect(options.body).toBe('{"status":"going"}');
        expect(wrapper.emitted('error')).toEqual([['']]);
        expect(wrapper.emitted('done')[0][0].event).toMatchObject({ my_status: 'going', guest_count: 4, spots_left: null });
        expect(ElNotification).toHaveBeenCalledWith({ type: 'success', message: 'RSVP updated. You\'re going!' });
        expect(ElMessageBox.alert).not.toHaveBeenCalled();
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
