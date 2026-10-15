import { describe, it, expect, vi, afterEach } from 'vitest';

const spaceRoute = () => ({ path: '/space/:space/', children: [{ path: 'home', name: 'space_feeds' }] });

// Imports a fresh copy of the entry, which registers its filters on fcom's hooks at load.
const loadPortal = async ({ utilReady = true } = {}) => {
    vi.resetModules();
    const filters = {};
    const util = { hooks: { addFilter: vi.fn((name, namespace, callback) => { filters[name] = callback; }) } };

    if (utilReady) {
        window.FluentCommunityUtil = util;
    }

    await import('../../../resources/fcom-portal/fcom-portal.js');

    return { filters, util };
};

afterEach(() => {
    delete window.FluentCommunityUtil;
});

describe('fcom-portal entry', () => {
    it("registers on fcom's hooks straight away when fcom is ready", async () => {
        const { util } = await loadPortal();

        expect(util.hooks.addFilter.mock.calls.map(([name, namespace]) => [name, namespace])).toEqual([
            ['fluent_com_portal_routes', 'fluent-event'],
            ['fluent_com_feed_content_components', 'fluent-event'],
            ['fluent_com_space_actions', 'fluent-event'],
            ['fluent_com_composer_config', 'fluent-event']
        ]);
    });

    it('waits for fcom when it loads first', async () => {
        const { util } = await loadPortal({ utilReady: false });
        expect(util.hooks.addFilter).not.toHaveBeenCalled();

        window.FluentCommunityUtil = util;
        document.dispatchEvent(new Event('fluentCommunityUtilReady'));

        expect(util.hooks.addFilter).toHaveBeenCalledTimes(4);
    });

    describe('routes', () => {
        it('adds the community events page and the single event page', async () => {
            const { filters } = await loadPortal();

            const routes = filters.fluent_com_portal_routes([spaceRoute()]);

            expect(routes.slice(1).map(({ path, name, meta }) => ({ path, name, meta }))).toEqual([
                { path: '/events', name: 'fe_events', meta: { active_menu: 'fe_events' } },
                { path: '/post/:feed_slug/event', name: 'fe_event_view', meta: { active_menu: 'fe_events' } }
            ]);
        });

        it("adds the Events and Create event tabs inside fcom's space route", async () => {
            const { filters } = await loadPortal();

            const [space] = filters.fluent_com_portal_routes([spaceRoute()]);

            expect(space.children.map(child => child.name)).toEqual(['space_feeds', 'fe_space_events', 'fe_space_create_event']);
            expect(space.children[1].meta).toEqual({ in_space: true, show_sidebar: false });
        });

        it('leaves spaces alone when fcom has no space route', async () => {
            const { filters } = await loadPortal();

            const routes = filters.fluent_com_portal_routes([{ path: '/', name: 'all_feeds' }]);

            expect(routes.map(route => route.name)).toEqual(['all_feeds', 'fe_events', 'fe_event_view']);
        });
    });

    it('renders event posts with the event card', async () => {
        const { filters } = await loadPortal();

        const components = filters.fluent_com_feed_content_components({ text: {} });

        expect(components.event.name).toBe('FluentEventCard');
        expect(components.text).toEqual({});
    });

    describe('space actions', () => {
        const space = (overrides = {}) => ({ slug: 'design', type: 'community', permissions: { can_create_post: true }, ...overrides });

        it('offers Create new event to a member who can post in a community space', async () => {
            const { filters } = await loadPortal();

            expect(filters.fluent_com_space_actions([], space())).toEqual([{
                key: 'fe_create_event',
                title: 'Create new event',
                route: { name: 'fe_space_create_event', params: { space: 'design' } }
            }]);
        });

        it('offers nothing without the event permission, post permission or a community space', async () => {
            const { filters } = await loadPortal();

            expect(filters.fluent_com_space_actions([], space({ type: 'course' }))).toEqual([]);
            expect(filters.fluent_com_space_actions([], space({ permissions: {} }))).toEqual([]);

            window.fluentEventFcomPortal.can_create_events = false;
            expect(filters.fluent_com_space_actions([], space())).toEqual([]);
        });
    });

    describe('composer', () => {
        const config = { surveyApp: true, documentsApp: true, videoApp: true, mediaApp: true, giphyApp: true, emojiApp: true };

        it('turns off attachment apps for an event post', async () => {
            const { filters } = await loadPortal();

            expect(filters.fluent_com_composer_config(config, { content_type: 'event' })).toEqual({
                surveyApp: false,
                documentsApp: false,
                videoApp: false,
                mediaApp: false,
                giphyApp: false,
                emojiApp: true
            });
        });

        it('leaves every other post as fcom configured it', async () => {
            const { filters } = await loadPortal();

            expect(filters.fluent_com_composer_config(config, { content_type: 'text' })).toBe(config);
            expect(filters.fluent_com_composer_config(config, null)).toBe(config);
        });
    });

    it('opens a published event post but returns a scheduled one to the space', async () => {
        const { filters } = await loadPortal();
        const [space] = filters.fluent_com_portal_routes([spaceRoute()]);
        const createShell = space.children.find(child => child.name === 'fe_space_create_event').component;
        const shell = { $route: { params: { space: 'design' } }, $router: { push: vi.fn() } };

        createShell.methods.posted.call(shell, { status: 'published', slug: 'meetup' });
        createShell.methods.posted.call(shell, { status: 'scheduled', slug: 'meetup' });

        expect(shell.$router.push.mock.calls).toEqual([
            [{ name: 'single_feed', params: { feed_slug: 'meetup' } }],
            [{ name: 'fe_space_events', params: { space: 'design' } }]
        ]);
    });
});
