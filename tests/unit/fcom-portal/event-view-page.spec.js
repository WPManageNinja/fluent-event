import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { t } from '../../../resources/fcom-portal/utils';
import EventViewPage from '../../../resources/fcom-portal/app/EventViewPage.vue';
import EventActions from '../../../resources/fcom-portal/EventActions';

const meetup = (overrides = {}) => ({
    id: 5,
    title: 'Community meetup',
    slug: 'community-meetup',
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
    ...overrides
});

// The URL names the space and the event; its post, which fcom reads it by, has a slug of its own.
const page = async (event, feed = {}) => {
    fetch.mockResolvedValueOnce(new Response(JSON.stringify({ feed_slug: 'meetup-post' })));
    fetch.mockResolvedValueOnce(new Response(JSON.stringify({
        feed: { slug: 'meetup-post', space: { slug: 'design', title: 'Design' }, xprofile: { display_name: 'Jhon Doe', avatar: '' }, fluent_event: event, ...feed }
    })));
    const router = { push: vi.fn(), back: vi.fn(), resolve: (route) => ({ href: `/portal/post/${route.params.feed_slug}` }) };
    const wrapper = mount(EventViewPage, {
        props: { spaceSlug: 'design', eventSlug: 'community-meetup', router, onFeed: vi.fn() },
        global: { mocks: { $t: t } }
    });
    await flushPromises();

    return { wrapper, vm: wrapper.vm, router, onFeed: wrapper.props('onFeed') };
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
        it('titles the tab with the event once it loads', async () => {
            window.fluentComAdmin.site_title = 'Acme Community';

            await page(meetup());

            expect(document.title).toBe('Community meetup - Acme Community');
        });

        it('leaves the tab title alone when the member left before the event loaded', async () => {
            document.title = 'All Feeds - Acme Community';
            fetch.mockResolvedValueOnce(new Response(JSON.stringify({ feed_slug: 'meetup-post' })));
            let answer;
            fetch.mockReturnValueOnce(new Promise((resolve) => { answer = resolve; }));
            const wrapper = mount(EventViewPage, {
                props: { spaceSlug: 'design', eventSlug: 'community-meetup', router: { push: vi.fn(), back: vi.fn() }, onFeed: vi.fn() },
                global: { mocks: { $t: t } }
            });
            await flushPromises();

            wrapper.unmount();
            answer(new Response(JSON.stringify({ feed: { slug: 'meetup-post', fluent_event: meetup() } })));
            await flushPromises();

            expect(document.title).toBe('All Feeds - Acme Community');
        });

        it("reads the event through fcom's post endpoint, so it is visible exactly when its post is", async () => {
            const { wrapper } = await page(meetup());

            expect(fetch.mock.calls[0][0]).toBe('https://example.test/wp-json/fluent-event/v2/community/events/by-slug?space=design&slug=community-meetup');
            expect(fetch.mock.calls[1][0]).toBe('https://example.test/wp-json/fluent-community/v2/feeds/meetup-post/by-slug');
            expect(wrapper.find('.fe-event-view__title').text()).toBe('Community meetup');
        });

        it("hands the post to the shell once its Comments card is on the page, for fcom's comment box", async () => {
            const { wrapper, onFeed } = await page(meetup());

            expect(wrapper.find('#fe-event-view-comments').exists()).toBe(true);
            expect(onFeed).toHaveBeenCalledWith(expect.objectContaining({ slug: 'meetup-post' }));
        });

        it('leaves the Comments card out when the post has comments turned off', async () => {
            const { wrapper, onFeed } = await page(meetup(), { meta: { comments_disabled: 'yes' } });

            expect(wrapper.find('#fe-event-view-comments').exists()).toBe(false);
            expect(onFeed).not.toHaveBeenCalled();
        });

        // The event page has no other way back to its post, its author or its space.
        it('names who posted the event and where, and links to the post', async () => {
            const { wrapper, router } = await page(meetup());
            const origin = wrapper.find('.fe-event-view__origin');

            expect(origin.text()).toMatch(/Posted by Jhon Doe in\s+Design/);
            expect(origin.find('.fe-event-view__origin-space').text()).toBe('Design');
            expect(origin.find('a').attributes('href')).toBe('/portal/post/meetup-post');

            await origin.find('a').trigger('click');
            expect(router.push).toHaveBeenCalledWith({ name: 'single_feed', params: { feed_slug: 'meetup-post' } });

            const { wrapper: global } = await page(meetup(), { space: null });
            expect(global.find('.fe-event-view__origin').text()).toContain('Posted by Jhon Doe on their profile');
        });

        // Bangla puts the space before the verb; one string lets the translation say so.
        it('lets a translation put the space name anywhere in the line', async () => {
            vi.stubGlobal('fluentEventFcomPortal', { ...window.fluentEventFcomPortal, i18n: { 'Posted by %1$s in %2$s': '%1$s %2$s-এ পোস্ট করেছেন' } });
            const { wrapper } = await page(meetup());
            const origin = wrapper.find('.fe-event-view__origin');

            expect(origin.text()).toContain('Jhon Doe Design-এ পোস্ট করেছেন');
            expect(origin.find('.fe-event-view__origin-space').text()).toBe('Design');
        });

        it('shows the unavailable state when no visible event has that slug in that space', async () => {
            fetch.mockResolvedValueOnce(new Response(JSON.stringify({ message: 'This event is not available.' }), { status: 404 }));
            const wrapper = mount(EventViewPage, {
                props: { spaceSlug: 'design', eventSlug: 'gone', router: {}, onFeed: vi.fn() },
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
            expect(heading).toContain('October 20, 2026');
            expect(heading).toContain('3:00 AM – 4:00 AM (Asia/Dhaka)');
            expect(heading).not.toContain('10:00 PM');

            expect(vm.day).toBe('19');
            expect(vm.dateLabel).toBe('October 19, 2026');
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

        it('ticks a countdown instead when the host chose one, and turns live at 0', async () => {
            // The interval too this time; flushPromises still needs the real setTimeout.
            vi.useRealTimers();
            vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] });
            vi.setSystemTime(new Date('2026-10-20T11:59:58Z'));
            const { wrapper } = await page(meetup({ start_display: 'countdown' }));
            const values = () => wrapper.findAll('.fe-countdown__value').map((value) => value.text());

            expect(values()).toEqual(['00', '00', '00', '02']);
            expect(wrapper.find('.fe-event-view__status').exists()).toBe(false);

            vi.advanceTimersByTime(2000);
            await flushPromises();

            expect(wrapper.find('.fe-countdown').exists()).toBe(false);
            expect(wrapper.find('.fe-live-badge').text()).toBe('Happening now');
        });

        // A suspended tab can miss the whole event between two ticks; reaching 0 then must not mean live.
        it('lands on Ended, not live, when the countdown finishes after the event is over', async () => {
            vi.useRealTimers();
            vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] });
            vi.setSystemTime(new Date('2026-10-20T11:59:58Z'));
            const { wrapper } = await page(meetup({ start_display: 'countdown', entry_type: 'none' }));

            // The event runs 12:00 to 14:00; the next tick comes at 15:00.
            vi.setSystemTime(new Date('2026-10-20T15:00:00Z'));
            vi.advanceTimersByTime(1000);
            await flushPromises();

            expect(wrapper.find('.fe-live-badge').exists()).toBe(false);
            expect(wrapper.find('.fe-event-view__status').text()).toBe('Ended');
            expect(wrapper.find('.fe-event-view__calendar').exists()).toBe(false);
        });

        it('marks an event in progress as happening now', async () => {
            vi.setSystemTime(new Date('2026-10-20T13:00:00Z'));
            const { wrapper } = await page(meetup());

            expect(wrapper.find('.fe-live-badge').text()).toBe('Happening now');
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

    describe('open', () => {
        it('offers a member the Register button, then marks them registered', async () => {
            expect((await page(meetup({ entry_type: 'open' }))).wrapper.find('.fe-event-view__rsvp button').text()).toBe('Register');
            expect((await page(meetup({ entry_type: 'open', my_status: 'going' }))).wrapper.find('.fe-rsvp__label').text()).toBe('Registered');
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

        it('drops the register note when the event is full and the member holds no seat', async () => {
            const full = (extra) => meetup({ venue_type: 'online', has_meeting_url: true, capacity: 2, guest_count: 2, is_full: true, ...extra });

            expect((await page(full())).wrapper.find('.fe-event-view__note').exists()).toBe(false);
            expect((await page(full({ waitlist: true }))).wrapper.find('.fe-event-view__note').exists()).toBe(false);
        });

        it('shows the venue and address for an in-person event', async () => {
            const { wrapper } = await page(meetup());

            expect(wrapper.find('.fe-event-view__venue').text()).toBe('Hall A');
            expect(wrapper.find('.fe-event-view__address').text()).toBe('12 Road');
        });
    });

    describe('add to calendar', () => {
        it('is offered once the member is going, or on any no-entry event', async () => {
            expect((await page(meetup())).vm.canAddToCalendar).toBe(false);
            expect((await page(meetup({ my_status: 'going' }))).vm.canAddToCalendar).toBe(true);
            expect((await page(meetup({ entry_type: 'none' }))).vm.canAddToCalendar).toBe(true);
            expect((await page(meetup({ entry_type: 'open' }))).vm.canAddToCalendar).toBe(false);
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
            const { wrapper } = await page(meetup({ my_status: 'going' }));
            const params = new URL(wrapper.find('a.fe-event-view__calendar-link').attributes('href')).searchParams;

            expect(params.get('text')).toBe('Community meetup');
            expect(params.get('dates')).toBe('20261020T120000Z/20261020T140000Z');
            expect(params.get('location')).toBe('Hall A, 12 Road');
        });

        it("uses the page itself as an online event's location while the link is hidden", async () => {
            const { vm } = await page(meetup({ venue_type: 'online', online_meeting_url: '' }));

            expect(vm.place).toBe(window.location.href);
        });

        it('downloads an .ics file named after the event', async () => {
            vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:ics');
            vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
            let link = null;
            vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function () {
                link = this;
            });
            const { wrapper } = await page(meetup({ entry_type: 'none' }));

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

        it('only goes back, with no fixed page, on a direct visit too', async () => {
            const { wrapper, router } = await page(meetup());
            window.history.replaceState(null, '');

            await wrapper.find('.fe-event-view__back').trigger('click');

            expect(router.back).toHaveBeenCalled();
            expect(router.push).not.toHaveBeenCalled();
        });
    });

    describe('capacity', () => {
        // Both switches on: the count and the spots left.
        const capped = (going, capacity = 10) => meetup({ guest_count: going, capacity, spots_left: Math.max(0, capacity - going), is_full: going >= capacity });

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

        it('offers the waitlist under the full notice when the event takes one', async () => {
            const { wrapper } = await page({ ...capped(10), waitlist: true });

            expect(wrapper.find('.fe-event-view__notice-desc').text()).toBe('Join the waitlist and the host will let you know if a spot opens up.');
            expect(wrapper.findComponent(EventActions).exists()).toBe(true);
        });

        it('tells a guest already on the waitlist they are on it, in place of the full notice', async () => {
            const { wrapper } = await page({ ...capped(10), waitlist: true, my_status: 'waitlisted' });

            expect(wrapper.find('.fe-event-view__notice-title').text()).toBe("You're on the waitlist");
            expect(wrapper.find('.fe-event-view__notice-desc').text()).toBe('The host will let you know if a spot opens up.');
            expect(wrapper.findComponent(EventActions).exists()).toBe(true);
        });

        it('drops the waitlist notice once the event has ended', async () => {
            const { wrapper } = await page({ ...ended(capped(10)), waitlist: true, my_status: 'waitlisted' });

            expect(wrapper.find('.fe-event-view__notice').exists()).toBe(false);
        });

        it('drops the register-to-see-the-link note on the waitlist path', async () => {
            const { wrapper } = await page({ ...capped(10), waitlist: true, my_status: 'waitlisted', venue_type: 'online', has_meeting_url: true, online_meeting_url: '' });

            expect(wrapper.find('.fe-event-view__note').exists()).toBe(false);
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

        // The reply to an answer carries the seats as they stand, so the meter, notice and RSVP move together.
        it('opens the event again once a guest gives up a seat on a full event', async () => {
            const { wrapper } = await page({ ...capped(6, 6), my_status: 'going' });

            wrapper.findComponent(EventActions).vm.$emit('done', { event: { ...wrapper.vm.event, my_status: 'not_going', guest_count: 5, spots_left: 1, is_full: false } });
            await wrapper.vm.$nextTick();

            expect(wrapper.find('.fe-capacity__head').text()).toBe('5 out of 6 going1 spot left');
            expect(wrapper.find('.fe-event-view__notice').exists()).toBe(false);
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
            expect((await page(meetup({ guest_count: null, capacity: 10, spots_left: null }))).wrapper.find('.fe-capacity').exists()).toBe(false);

            const uncapped = (await page(meetup({ guest_count: 4, capacity: null, spots_left: null }))).wrapper;
            expect(uncapped.find('.fe-capacity').exists()).toBe(false);
            expect(uncapped.find('.fe-event-view__count').text()).toBe('4 going');
        });

        it('shows only the going count when the spots left are hidden', async () => {
            const { wrapper } = await page(meetup({ guest_count: 7, capacity: 10, spots_left: null }));

            expect(wrapper.find('.fe-capacity').exists()).toBe(false);
            expect(wrapper.find('.fe-event-view__count').text()).toBe('7 going');
        });

        it('shows only the spots left when the count is hidden, and nothing once the event ends', async () => {
            const spotsOnly = meetup({ guest_count: null, capacity: 10, spots_left: 3 });
            const { wrapper } = await page(spotsOnly);

            expect(wrapper.find('.fe-capacity').exists()).toBe(false);
            expect(wrapper.find('.fe-event-view__count').text()).toBe('3 spots left');

            expect((await page(ended(spotsOnly))).wrapper.find('.fe-event-view__count').exists()).toBe(false);
        });
    });
});
