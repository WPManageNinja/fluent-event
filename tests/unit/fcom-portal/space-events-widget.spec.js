import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises, RouterLinkStub } from '@vue/test-utils';
import SpaceEventsWidget from '../../../resources/fcom-portal/SpaceEventsWidget';

const json = (body, status = 200) => new Response(JSON.stringify(body), { status });

const event = (id, overrides = {}) => ({
    id,
    title: `Meetup ${id}`,
    slug: `meetup-${id}`,
    space_slug: 'design',
    start_at_site: '2026-10-20 18:00:00',
    ...overrides
});

const widget = async (space_profile = { slug: 'design' }) => {
    const wrapper = mount(SpaceEventsWidget, { props: { space_profile }, global: { stubs: { RouterLink: RouterLinkStub } } });
    await flushPromises();
    return wrapper;
};

const query = (call = fetch.mock.calls.length - 1) => Object.fromEntries(new URL(fetch.mock.calls[call][0]).searchParams);

beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn(async () => json({ events: [event(1), event(2)] })));
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('SpaceEventsWidget', () => {
    it("asks for the space's next three upcoming events", async () => {
        await widget();

        expect(query()).toEqual({ space: 'design', scope: 'upcoming', per_page: '3' });
    });

    it('lists each event with its date, linked to its page, and links to all of them', async () => {
        const wrapper = await widget();
        const links = wrapper.findAllComponents(RouterLinkStub);

        expect(wrapper.findAll('.fe-space-events__item')).toHaveLength(2);
        expect(wrapper.find('.fe-space-events__date').text()).toBe('October 20, 2026 · 6:00 PM');
        expect(links[0].props('to')).toEqual({ name: 'fe_space_events', params: { space: 'design' } });
        expect(links[1].props('to')).toEqual({ name: 'fe_event_view', params: { space: 'design', event_slug: 'meetup-1' } });
        expect(links[1].text()).toBe('Meetup 1');
    });

    it('says so when the space has no upcoming events, without a View all link', async () => {
        fetch.mockResolvedValueOnce(json({ events: [] }));
        const wrapper = await widget();

        expect(wrapper.find('.fe-space-events__note').text()).toBe('No upcoming events');
        expect(wrapper.findComponent(RouterLinkStub).exists()).toBe(false);
    });

    it('shows an error when the list fails to load', async () => {
        fetch.mockResolvedValueOnce(json({ message: 'nope' }, 500));
        const wrapper = await widget();

        expect(wrapper.find('[role="alert"]').text()).toBe('Something went wrong. Please try again.');
    });

    it('reloads for the new space when the member moves to another one', async () => {
        const wrapper = await widget();

        await wrapper.setProps({ space_profile: { slug: 'books' } });
        await flushPromises();

        expect(query().space).toBe('books');
    });
});
