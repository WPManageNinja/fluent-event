import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { t } from '../../../resources/fcom-portal/utils';
import EventViewPage from '../../../resources/fcom-portal/app/EventViewPage.vue';

const meetup = (overrides = {}) => ({
    id: 5,
    title: 'Community meetup',
    description: '',
    start_at: '2026-10-20 12:00:00',
    end_at: '2026-10-20 14:00:00',
    start_at_local: '2026-10-20 12:00:00',
    end_at_local: '2026-10-20 14:00:00',
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

    describe('when it happens', () => {
        it("labels the time with the event zone's offset on the event date", async () => {
            const { vm } = await page(meetup({
                timezone: 'America/New_York',
                start_at: '2026-11-10 23:00:00',
                start_at_local: '2026-11-10 18:00:00',
                end_at_local: '2026-11-10 20:00:00'
            }));

            expect(vm.eventTimeLabel).toBe('6:00 PM – 8:00 PM GMT-5');
        });

        it("adds the visitor's own time when their clock differs from the event's", async () => {
            const { vm } = await page(meetup({
                timezone: 'Asia/Dhaka',
                start_at_local: '2026-10-20 18:00:00',
                end_at_local: '2026-10-20 20:00:00'
            }));

            expect(vm.localTimeLabel).toBe('12:00 PM – 2:00 PM in your time');
        });

        it("adds nothing when the visitor shares the event's zone", async () => {
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
