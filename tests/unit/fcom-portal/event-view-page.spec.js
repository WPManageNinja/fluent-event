import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { t } from '../../../resources/fcom-portal/utils';
import EventViewPage from '../../../resources/fcom-portal/app/EventViewPage.vue';
import EventActions from '../../../resources/fcom-portal/EventActions';

const meetup = (overrides = {}) => ({
    id: 5,
    title: 'Community meetup',
    description: '',
    start_at: '2026-10-20 12:00:00',
    end_at: '2026-10-20 14:00:00',
    start_at_local: '2026-10-20 12:00:00',
    end_at_local: '2026-10-20 14:00:00',
    start_at_site: '2026-10-20 12:00:00',
    end_at_site: '2026-10-20 14:00:00',
    timezone: 'UTC',
    venue_type: 'offline',
    venue: 'Hall A',
    address: '12 Road',
    online_meeting_url: '',
    entry_type: 'rsvp',
    my_status: null,
    is_past: false,
    registration_open: true,
    ...overrides
});

const page = async (event, feed = {}) => {
    fetch.mockResolvedValueOnce(new Response(JSON.stringify({
        feed: { slug: 'community-meetup', space: { slug: 'design' }, fluent_event: event, ...feed }
    })));
    const router = { push: vi.fn(), back: vi.fn() };
    const wrapper = mount(EventViewPage, {
        props: { feedSlug: 'community-meetup', router },
        global: { mocks: { $t: t } }
    });
    await flushPromises();

    return { wrapper, vm: wrapper.vm, router };
};

beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn());
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-14T12:00:00Z'));
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.history.replaceState(null, '');
});

describe('EventViewPage', () => {
    describe('loading', () => {
        it("reads the event through fcom's post endpoint, so it is visible exactly when its post is", async () => {
            const { wrapper } = await page(meetup());

            expect(fetch.mock.calls[0][0]).toBe('https://example.test/wp-json/fluent-community/v2/feeds/community-meetup/by-slug');
            expect(wrapper.find('.fe-event-view__title').text()).toBe('Community meetup');
        });

        it('shows the unavailable state when the post is gone', async () => {
            fetch.mockResolvedValueOnce(new Response(JSON.stringify({ message: 'Not found' }), { status: 404 }));
            const wrapper = mount(EventViewPage, {
                props: { feedSlug: 'gone', router: {} },
                global: { mocks: { $t: t } }
            });
            await flushPromises();

            expect(wrapper.find('el-empty-stub').attributes('description')).toBe('This event is not available.');
        });
    });

    describe('layout', () => {
        // Sharing grid rows with a taller card stretched the title's row and left a gap above a short description.
        it('stacks the title and the description in their own column beside the card', async () => {
            const { wrapper } = await page(meetup({ description: '<p>Bring a laptop.</p>' }));
            const main = wrapper.find('.fe-event-view__grid > .fe-event-view__main');

            expect(main.findAll(':scope > div').map(column => column.classes()[0])).toEqual(['fe-event-view__intro', 'fe-event-view__body']);
            expect(main.find('.fe-event-view__description').text()).toBe('Bring a laptop.');
            expect(wrapper.find('.fe-event-view__grid > .fe-event-view__side').exists()).toBe(true);
        });
    });

    describe('when it happens', () => {
        it("labels the time with the event's timezone name", async () => {
            const { vm } = await page(meetup({
                timezone: 'America/New_York',
                start_at: '2026-11-10 23:00:00',
                start_at_local: '2026-11-10 18:00:00',
                end_at_local: '2026-11-10 20:00:00'
            }));

            expect(vm.eventTimeLabel).toBe('6:00 PM – 8:00 PM (America/New_York)');
        });

        it("heads the page with the site's clock and adds it under the event's own in the side card", async () => {
            vi.stubGlobal('fluentEventFcomPortal', { ...window.fluentEventFcomPortal, timezone: 'Asia/Dhaka' });
            const { wrapper, vm } = await page(meetup({
                timezone: 'Europe/London',
                start_at_local: '2026-10-19 22:00:00',
                end_at_local: '2026-10-19 23:00:00',
                start_at_site: '2026-10-20 03:00:00',
                end_at_site: '2026-10-20 04:00:00'
            }));

            const heading = wrapper.find('.fe-event-view__meta').text();
            expect(heading).toContain('Tuesday, October 20, 2026');
            expect(heading).toContain('3:00 AM – 4:00 AM (Asia/Dhaka)');
            expect(heading).not.toContain('10:00 PM');

            expect(vm.day).toBe('19');
            expect(vm.dateLabel).toBe('Monday, October 19, 2026');
            expect(vm.localTimeLabel).toBe('Oct 20, 3:00 AM – 4:00 AM (Asia/Dhaka)');
        });

        it("adds nothing when the site's clock reads the same as the event's", async () => {
            const { wrapper } = await page(meetup());

            expect(wrapper.find('.fe-event-view__when-local').exists()).toBe(false);
        });

        it('spells out both dates for a multi-day event', async () => {
            const { vm } = await page(meetup({
                timezone: '',
                start_at_local: '2026-10-20 18:00:00',
                end_at_local: '2026-10-22 12:00:00'
            }));

            expect(vm.dateLabel).toBe('Oct 20 – Oct 22, 2026');
            expect(vm.eventTimeLabel).toBe('Oct 20, 6:00 PM – Oct 22, 12:00 PM');
        });

        it('counts down to an upcoming event', async () => {
            const { wrapper } = await page(meetup());

            expect(wrapper.find('.fe-event-view__status').text()).toBe('Starts in 6 days');
        });

        it('marks an event in progress as happening now', async () => {
            vi.setSystemTime(new Date('2026-10-20T13:00:00Z'));
            const { wrapper } = await page(meetup());

            expect(wrapper.find('.fe-live-badge').text()).toBe('Happening now');
        });
    });

    describe('capacity', () => {
        const capped = (going, capacity = 10) => meetup({ guest_count: going, capacity, is_full: going >= capacity });

        it('meters the seats taken and counts the ones left', async () => {
            const { wrapper } = await page(capped(7));

            expect(wrapper.find('.fe-capacity__head').text()).toBe('7 out of 10 going3 spots left');
            expect(wrapper.find('.fe-capacity__fill').attributes('style')).toContain('width: 70%');
            expect(wrapper.find('.fe-event-view__count').exists()).toBe(false);
        });

        it('says one spot in the singular', async () => {
            const { wrapper } = await page(capped(9));

            expect(wrapper.find('.fe-capacity__status').text()).toBe('1 spot left');
        });

        it('tells a member without a seat the event is full in place of the RSVP, under a full meter', async () => {
            const { wrapper } = await page(capped(10));

            expect(wrapper.find('.fe-event-view__notice-title').text()).toBe('Event full');
            expect(wrapper.find('.fe-capacity__head').text()).toBe('10 out of 10 going');
            expect(wrapper.find('.fe-capacity__fill').attributes('style')).toContain('width: 100%');
            expect(wrapper.find('.fe-event-view__count').exists()).toBe(false);
            expect(wrapper.findComponent(EventActions).exists()).toBe(false);
        });

        it('says the event is full even when the host hides the count, with no meter', async () => {
            const { wrapper } = await page(meetup({ guest_count: null, capacity: 10, is_full: true }));

            expect(wrapper.find('.fe-event-view__notice').exists()).toBe(true);
            expect(wrapper.find('.fe-capacity').exists()).toBe(false);
        });

        it('keeps the full meter and the RSVP for a guest who holds a seat', async () => {
            const { wrapper } = await page({ ...capped(10), my_status: 'going' });

            expect(wrapper.find('.fe-event-view__notice').exists()).toBe(false);
            expect(wrapper.find('.fe-capacity__status').text()).toBe('Event full');
            expect(wrapper.find('.fe-capacity__fill').attributes('style')).toContain('width: 100%');
            expect(wrapper.findComponent(EventActions).exists()).toBe(true);
        });

        const ended = (event) => ({ ...event, start_at: '2026-10-10 12:00:00', end_at: '2026-10-10 14:00:00', is_past: true });

        it('keeps the meter on an ended event, without spots left or a full notice while seats were left', async () => {
            const { wrapper } = await page(ended(capped(7)));

            expect(wrapper.find('.fe-capacity__head').text()).toBe('7 out of 10 going');
            expect(wrapper.find('.fe-capacity__fill').attributes('style')).toContain('width: 70%');
            expect(wrapper.find('.fe-event-view__notice').exists()).toBe(false);
        });

        it('keeps the full notice on an ended event that filled up', async () => {
            const { wrapper } = await page(ended(capped(10)));

            expect(wrapper.find('.fe-capacity__fill').attributes('style')).toContain('width: 100%');
            expect(wrapper.find('.fe-event-view__notice').exists()).toBe(true);
        });

        it('shows no meter when the host hides the count or set no capacity', async () => {
            expect((await page(meetup({ guest_count: null, capacity: 10 }))).wrapper.find('.fe-capacity').exists()).toBe(false);

            const uncapped = (await page(meetup({ guest_count: 4, capacity: null }))).wrapper;
            expect(uncapped.find('.fe-capacity').exists()).toBe(false);
            expect(uncapped.find('.fe-event-view__count').text()).toBe('4 going');
        });
    });

    describe('registration window', () => {
        it('tells a member when registration opens, in place of the RSVP', async () => {
            const { wrapper } = await page(meetup({ registration_open: false, registration_opens_at: '2026-10-18 09:00:00' }));

            expect(wrapper.find('.fe-event-view__notice-title').text()).toBe('Registration not open yet');
            expect(wrapper.find('.fe-event-view__notice-desc').text()).toBe('Registration opens Sun, Oct 18, 2026 · 9:00 AM.');
            expect(wrapper.findComponent(EventActions).exists()).toBe(false);
        });

        it('says registration closed before saying the event is full', async () => {
            const { wrapper } = await page(meetup({ registration_open: false, capacity: 10, guest_count: 10, is_full: true }));

            expect(wrapper.find('.fe-event-view__notice-title').text()).toBe('Registration closed');
            expect(wrapper.findComponent(EventActions).exists()).toBe(false);
        });

        it('keeps the RSVP for a member already going once registration closes', async () => {
            const { wrapper } = await page(meetup({ registration_open: false, my_status: 'going' }));

            expect(wrapper.find('.fe-event-view__notice').exists()).toBe(false);
            expect(wrapper.findComponent(EventActions).exists()).toBe(true);
        });

        it('tells a member who said not going that registration has not opened', async () => {
            const { wrapper } = await page(meetup({ registration_open: false, registration_opens_at: '2026-10-18 09:00:00', my_status: 'not_going' }));

            expect(wrapper.find('.fe-event-view__notice-title').text()).toBe('Registration not open yet');
            expect(wrapper.findComponent(EventActions).exists()).toBe(false);
        });

        it('says registration closed in place of Join waitlist on a full event', async () => {
            const { wrapper } = await page(meetup({ registration_open: false, capacity: 10, guest_count: 10, is_full: true, waitlist: true }));

            expect(wrapper.find('.fe-event-view__notice-title').text()).toBe('Registration closed');
            expect(wrapper.text()).not.toContain('Join waitlist');
        });

        it('still tells a waitlisted member where they stand once registration closes', async () => {
            const { wrapper } = await page(meetup({ registration_open: false, my_status: 'waitlisted' }));

            expect(wrapper.find('.fe-event-view__notice-title').text()).toBe('You are on the waitlist');
        });

        it('says nothing about registration once the event has ended', async () => {
            const { wrapper } = await page(meetup({
                start_at: '2026-10-10 12:00:00', end_at: '2026-10-10 14:00:00', is_past: true,
                registration_open: false, registration_closes_at: null
            }));

            expect(wrapper.text()).not.toContain('Registration');
            expect(wrapper.findComponent(EventActions).exists()).toBe(false);
        });

        it('keeps the RSVP while the event is under way and registration has no closing time', async () => {
            vi.setSystemTime(new Date('2026-10-20T13:00:00Z'));
            const { wrapper } = await page(meetup());

            expect(wrapper.find('.fe-event-view__notice').exists()).toBe(false);
            expect(wrapper.findComponent(EventActions).exists()).toBe(true);
            expect(wrapper.find('.fe-event-view__note').exists()).toBe(false);
        });

        it('shows no closing time once registration is shut', async () => {
            const { wrapper } = await page(meetup({ registration_open: false, registration_closes_at: '2026-10-19 18:00:00' }));

            expect(wrapper.find('.fe-event-view__note').exists()).toBe(false);
        });

        it('says when registration closes while it is open', async () => {
            const { wrapper } = await page(meetup({ registration_closes_at: '2026-10-19 18:00:00' }));

            expect(wrapper.find('.fe-event-view__note').text()).toBe('Registration closes Mon, Oct 19, 2026 · 6:00 PM');
        });
    });

    describe('waitlist', () => {
        const full = (overrides = {}) => meetup({ guest_count: 10, capacity: 10, is_full: true, waitlist: true, ...overrides });

        it('invites a member without a seat to join the waitlist', async () => {
            const { wrapper } = await page(full());

            expect(wrapper.find('.fe-event-view__notice-title').text()).toBe('Event full');
            expect(wrapper.find('.fe-event-view__notice-desc').text()).toBe('Join the waitlist to be moved to going if a seat opens up.');
            expect(wrapper.findComponent(EventActions).exists()).toBe(true);
        });

        it.each([
            ['full', true],
            ['with a seat free', false],
        ])('tells a waitlisted member where they stand, %s, with no RSVP', async (label, isFull) => {
            const { wrapper } = await page(full({ my_status: 'waitlisted', is_full: isFull }));

            expect(wrapper.find('.fe-event-view__notice-title').text()).toBe('You are on the waitlist');
            expect(wrapper.findComponent(EventActions).exists()).toBe(false);
        });

        it.each([
            ['full', true],
            ['with a seat free', false],
        ])('tells a cancelled member their registration is cancelled, %s, with no RSVP', async (label, isFull) => {
            const { wrapper } = await page(full({ my_status: 'cancelled', is_full: isFull }));

            expect(wrapper.find('.fe-event-view__notice--cancelled .fe-event-view__notice-title').text()).toBe('Your registration has been cancelled');
            expect(wrapper.findComponent(EventActions).exists()).toBe(false);
        });

        it('keeps a checked-in guest on the normal Going RSVP', async () => {
            const { wrapper } = await page(full({ my_status: 'checked_in' }));

            expect(wrapper.find('.fe-event-view__notice').exists()).toBe(false);
            expect(wrapper.find('.fe-rsvp__label').text()).toBe('Going');
        });

        it('closes the waitlist once the event has ended', async () => {
            const { wrapper } = await page(full({ start_at: '2026-10-10 12:00:00', end_at: '2026-10-10 14:00:00', is_past: true }));

            expect(wrapper.find('.fe-event-view__notice-desc').text()).toBe('This event is full and no longer taking registrations.');
            expect(wrapper.findComponent(EventActions).exists()).toBe(false);
        });
    });

    describe('ended', () => {
        const over = { start_at: '2026-10-10 12:00:00', end_at: '2026-10-10 14:00:00', is_past: true };

        it('says Ended once, in the status, with no RSVP slot repeating it', async () => {
            const { wrapper } = await page(meetup({ ...over, guest_count: 12 }));

            expect(wrapper.find('.fe-event-view__status--ended').text()).toBe('Ended');
            expect(wrapper.findComponent(EventActions).exists()).toBe(false);
            expect(wrapper.find('.fe-event-view__count').text()).toBe('12 going');
        });

        it('shows an open event no RSVP slot either', async () => {
            const { wrapper } = await page(meetup({ ...over, entry_type: 'open' }));

            expect(wrapper.find('.fe-event-view__rsvp').exists()).toBe(false);
        });
    });

    describe('getting there', () => {
        it('offers a join button only for an http(s) meeting link', async () => {
            const online = (url) => meetup({ venue_type: 'online', online_meeting_url: url });

            expect((await page(online('javascript:alert(1)'))).wrapper.find('.fe-event-view__join').exists()).toBe(false);
            expect((await page(online('https://meet.example.test/abc'))).wrapper.find('.fe-event-view__join').attributes('href'))
                .toBe('https://meet.example.test/abc');
        });

        it('explains a hidden meeting link to a member who has not registered', async () => {
            const { wrapper } = await page(meetup({ venue_type: 'online', has_meeting_url: true }));

            expect(wrapper.find('.fe-event-view__note').text()).toBe('Please register for the event to see the meeting link.');
        });

        it('shows the venue and address for an in-person event', async () => {
            const { wrapper } = await page(meetup());

            expect(wrapper.find('.fe-event-view__venue').text()).toBe('Hall A');
            expect(wrapper.find('.fe-event-view__address').text()).toBe('12 Road');
        });
    });

    describe('add to calendar', () => {
        it('is offered once the member is going, or on any open event', async () => {
            expect((await page(meetup())).vm.canAddToCalendar).toBe(false);
            expect((await page(meetup({ my_status: 'going' }))).vm.canAddToCalendar).toBe(true);
            expect((await page(meetup({ entry_type: 'open' }))).vm.canAddToCalendar).toBe(true);
        });

        it('is withdrawn once the event has ended', async () => {
            expect((await page(meetup({ my_status: 'going', is_past: true }))).vm.canAddToCalendar).toBe(false);
        });

        it('writes the description as plain text with bullets and line breaks', async () => {
            const { vm } = await page(meetup({
                description: '<p>Bring snacks</p><ul><li>Agenda</li><li>Q&amp;A</li></ul>'
            }));

            expect(vm.summary).toBe(`Bring snacks\n• Agenda\n• Q&A\n\n${window.location.href}`);
        });

        it('keeps the description after an RSVP, whose answer carries none', async () => {
            const { wrapper, vm } = await page(meetup({ description: '<p>Bring snacks</p>' }));
            const { description, ...answer } = meetup({ my_status: 'going' });

            wrapper.findComponent(EventActions).vm.$emit('done', { event: answer });

            expect(vm.event.my_status).toBe('going');
            expect(vm.event.description).toBe('<p>Bring snacks</p>');
        });

        it('caps the description at 600 characters', async () => {
            const { vm } = await page(meetup({ description: `<p>${'a'.repeat(1000)}</p>` }));

            expect(vm.summary).toBe(`${'a'.repeat(600)}\n\n${window.location.href}`);
        });

        it('builds a Google Calendar link in UTC with the venue as location', async () => {
            const { vm } = await page(meetup({ my_status: 'going' }));
            const params = new URL(vm.googleCalendarUrl).searchParams;

            expect(params.get('text')).toBe('Community meetup');
            expect(params.get('dates')).toBe('20261020T120000Z/20261020T140000Z');
            expect(params.get('location')).toBe('Hall A, 12 Road');
        });

        it("uses the page itself as an online event's location while the link is hidden", async () => {
            const { vm } = await page(meetup({ venue_type: 'online', online_meeting_url: '' }));

            expect(vm.place).toBe(window.location.href);
        });

        it('downloads an .ics file named after the post', async () => {
            vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:ics');
            vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
            let link = null;
            vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function () {
                link = this;
            });
            const { wrapper } = await page(meetup({ entry_type: 'open' }));

            await wrapper.find('button.fe-event-view__calendar-link').trigger('click');

            expect(link.download).toBe('community-meetup.ics');
        });
    });

    describe('back', () => {
        it('returns to wherever the member came from inside the portal', async () => {
            const { wrapper, router } = await page(meetup());
            window.history.replaceState({ back: '/space/design/events' }, '');

            await wrapper.find('.fe-event-view__back').trigger('click');

            expect(router.back).toHaveBeenCalled();
            expect(router.push).not.toHaveBeenCalled();
        });

        it("falls back to the space's events, or all events, on a direct visit", async () => {
            const inSpace = await page(meetup());
            await inSpace.wrapper.find('.fe-event-view__back').trigger('click');

            const global = await page(meetup(), { space: null });
            await global.wrapper.find('.fe-event-view__back').trigger('click');

            expect(inSpace.router.push).toHaveBeenCalledWith({ name: 'fe_space_events', params: { space: 'design' } });
            expect(global.router.push).toHaveBeenCalledWith({ name: 'fe_events' });
        });
    });
});
