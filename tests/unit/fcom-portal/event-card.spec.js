import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises, RouterLinkStub } from '@vue/test-utils';
import EventCard from '../../../resources/fcom-portal/EventCard';
import EventActions from '../../../resources/fcom-portal/EventActions';

const meetup = (overrides = {}) => ({
    id: 5,
    title: 'Community meetup',
    start_at: '2026-10-20 12:00:00',
    end_at: '2026-10-20 14:00:00',
    start_at_site: '2026-10-20 18:00:00',
    end_at_site: '2026-10-20 20:00:00',
    venue_type: 'offline',
    entry_type: 'rsvp',
    guest_count: 8,
    my_status: null,
    is_past: false,
    edit_url: '',
    ...overrides
});

const card = (feed = { slug: 'community-meetup', fluent_event: meetup() }, props = {}, hasEventPage = true) =>
    mount(EventCard, { props: { feed, ...props }, global: { mocks: { $router: { hasRoute: () => hasEventPage } } } });

describe('EventCard', () => {
    it('says the event is unavailable when the post carries none', () => {
        const wrapper = card({ slug: 'gone', fluent_event: null });

        expect(wrapper.find('.fe-event-card__unavailable').text()).toBe('This event is not available.');
    });

    it("links the title to the event's own page", () => {
        const link = card().findComponent(RouterLinkStub);

        expect(link.props('to')).toEqual({ name: 'fe_event_view', params: { feed_slug: 'community-meetup' } });
        expect(link.text()).toBe('Community meetup');
    });

    it('shows a plain title when the post has no slug yet', () => {
        const wrapper = card({ fluent_event: meetup() });

        expect(wrapper.findComponent(RouterLinkStub).exists()).toBe(false);
        expect(wrapper.find('.fe-event-card__title').text()).toBe('Community meetup');
    });

    it("links the title to the portal page by URL in fcom's admin app, which has no event page route", () => {
        const wrapper = card(undefined, {}, false);
        const link = wrapper.find('a.fe-event-card__title-link');

        expect(wrapper.findComponent(RouterLinkStub).exists()).toBe(false);
        expect(link.attributes('href')).toBe('https://example.test/portal/post/community-meetup/event');
        expect(link.text()).toBe('Community meetup');
    });

    describe('a post handed over without its event (fcom moderation reports)', () => {
        beforeEach(() => {
            vi.stubGlobal('fetch', vi.fn());
        });

        afterEach(() => {
            vi.unstubAllGlobals();
        });

        it('loads the event through the post', async () => {
            fetch.mockResolvedValueOnce(new Response(JSON.stringify({ feed: { fluent_event: meetup() } })));
            const wrapper = card({ id: 1466, slug: 'community-meetup' });

            expect(wrapper.find('.fe-event-card__skeleton').attributes('role')).toBe('status');
            expect(wrapper.find('.fe-event-card__unavailable').exists()).toBe(false);
            await flushPromises();

            expect(fetch.mock.calls[0][0]).toBe('https://example.test/wp-json/fluent-community/v2/feeds/1466/by-id');
            expect(wrapper.find('.fe-event-card__skeleton').exists()).toBe(false);
            expect(wrapper.find('.fe-event-card__title').text()).toBe('Community meetup');
        });

        it('says the event is unavailable when the post cannot be loaded', async () => {
            fetch.mockResolvedValueOnce(new Response(JSON.stringify({ message: 'Not found' }), { status: 404 }));
            const wrapper = card({ id: 1466, slug: 'gone' });
            await flushPromises();

            expect(wrapper.find('.fe-event-card__unavailable').exists()).toBe(true);
        });

        it('does not load anything when the post carries its event, even an empty one', () => {
            card({ slug: 'gone', fluent_event: null });

            expect(fetch).not.toHaveBeenCalled();
        });
    });

    it('shows the date chip and the start time', () => {
        const wrapper = card();

        expect(wrapper.find('.fe-date-chip__month').text()).toBe('Oct');
        expect(wrapper.find('.fe-date-chip__day').text()).toBe('20');
        expect(wrapper.find('.fe-event-card__meta').text()).toBe('Tue, Oct 20, 2026 · 6:00 PM');
    });

    it('says whether the event is online or in person', () => {
        expect(card().find('.fe-event-card__meta--venue').text()).toBe('In person');
        expect(card({ fluent_event: meetup({ venue_type: 'online' }) }).find('.fe-event-card__meta--venue').text()).toBe('Online');
    });

    it('offers RSVP with the going count on an RSVP event', () => {
        const wrapper = card();

        expect(wrapper.findComponent(EventActions).exists()).toBe(true);
        expect(wrapper.find('.fe-event-card__count').text()).toBe('8 going');
    });

    it('offers no actions on an open event or a nested card', () => {
        expect(card({ fluent_event: meetup({ entry_type: 'open' }) }).find('.fe-event-card__actions').exists()).toBe(false);
        expect(card(undefined, { is_child: true }).find('.fe-event-card__actions').exists()).toBe(false);
    });

    it('takes the event an RSVP hands back', async () => {
        const wrapper = card();

        wrapper.findComponent(EventActions).vm.$emit('done', { event: meetup({ my_status: 'going', guest_count: 9 }) });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.fe-event-card__count').text()).toBe('9 going');
    });

    it('shows an RSVP failure as an alert', async () => {
        const wrapper = card();

        wrapper.findComponent(EventActions).vm.$emit('error', 'This event has ended.');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="alert"]').text()).toBe('This event has ended.');
    });

    it('follows the post when fcom swaps its event', async () => {
        const wrapper = card();

        await wrapper.setProps({ feed: { slug: 'community-meetup', fluent_event: meetup({ title: 'Renamed meetup' }) } });

        expect(wrapper.find('.fe-event-card__title').text()).toBe('Renamed meetup');
    });
});
