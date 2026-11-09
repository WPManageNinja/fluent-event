import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import dayjs from 'dayjs';
import { expectKnownIssue } from '../known-issue';

const spaceRoute = () => ({ path: '/space/:space/', children: [{ path: 'home', name: 'space_feeds' }] });

// Imports a fresh copy of the entry, which registers its filters on fcom's hooks at load.
const loadPortal = async () => {
    vi.resetModules();
    const filters = {};
    const util = { hooks: { addFilter: vi.fn((name, namespace, callback) => { filters[name] = callback; }) } };

    window.FluentCommunityUtil = util;

    await import('../../../resources/fcom-portal/fcom-portal.js');

    return { filters, util };
};

afterEach(() => {
    delete window.FluentCommunityUtil;
});

describe('fcom-portal entry', () => {
    it("registers on fcom's hooks as it loads", async () => {
        const { util } = await loadPortal();

        expect(util.hooks.addFilter.mock.calls.map(([name, namespace]) => [name, namespace])).toEqual([
            ['fluent_com_portal_routes', 'fluent-event'],
            ['fluent_com_feed_content_components', 'fluent-event'],
            ['fluent_com_space_actions', 'fluent-event'],
            ['fluent_com_feed_actions', 'fluent-event'],
            ['fluent_com_composer_config', 'fluent-event']
        ]);
    });

    describe('routes', () => {
        it('adds the community events page, the create page and the community-wide event page', async () => {
            const { filters } = await loadPortal();

            const routes = filters.fluent_com_portal_routes([spaceRoute()]);

            expect(routes.slice(1).map(({ path, name, meta }) => ({ path, name, meta }))).toEqual([
                { path: '/events', name: 'fe_events', meta: { active_menu: 'fe_events' } },
                { path: '/create-new-event', name: 'fe_create_event', meta: { active_menu: 'fe_events' } },
                { path: '/event/:event_slug', name: 'fe_global_event_view', meta: { active_menu: 'fe_events' } }
            ]);
        });

        it("adds the Events tab and the single event page inside fcom's space route", async () => {
            const { filters } = await loadPortal();

            const [space] = filters.fluent_com_portal_routes([spaceRoute()]);

            expect(space.children.map(({ path, name, meta }) => ({ path, name, meta }))).toEqual([
                expect.objectContaining({ name: 'space_feeds' }),
                { path: 'events', name: 'fe_space_events', meta: { in_space: true, show_sidebar: false } },
                { path: 'event/:event_slug', name: 'fe_event_view', meta: { in_space: true, show_sidebar: false } }
            ]);
        });

        it('frames the community-wide event page itself, since no space layout does', async () => {
            const { filters } = await loadPortal();
            const route = filters.fluent_com_portal_routes([spaceRoute()]).find(r => r.name === 'fe_global_event_view');

            expect(route.component.template).toContain('fe-fcom-portal-page--global_event_view');
        });

        it('leaves spaces alone when fcom has no space route', async () => {
            const { filters } = await loadPortal();

            const routes = filters.fluent_com_portal_routes([{ path: '/', name: 'all_feeds' }]);

            expect(routes.map(route => route.name)).toEqual(['all_feeds', 'fe_events', 'fe_create_event', 'fe_global_event_view']);
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
                route: { name: 'fe_create_event', query: { space: 'design' } }
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

    describe('feed actions', () => {
        it('offers Create new event, without a space, to an event creator', async () => {
            const { filters } = await loadPortal();

            expect(filters.fluent_com_feed_actions([])).toEqual([{
                key: 'fe_create_event',
                title: 'Create new event',
                route: { name: 'fe_create_event' }
            }]);
        });

        it('offers nothing without the event permission', async () => {
            const { filters } = await loadPortal();

            window.fluentEventFcomPortal.can_create_events = false;
            expect(filters.fluent_com_feed_actions([])).toEqual([]);
        });
    });

    describe('composer', () => {
        const config = { surveyApp: true, documentsApp: true, videoApp: true, mediaApp: true, giphyApp: true, emojiApp: true };

        it('turns off attachment apps and profile posting for an event post', async () => {
            const { filters } = await loadPortal();

            expect(filters.fluent_com_composer_config(config, { content_type: 'event' })).toEqual({
                surveyApp: false,
                documentsApp: false,
                videoApp: false,
                mediaApp: false,
                giphyApp: false,
                emojiApp: true,
                selfPost: false
            });
        });

        it('leaves every other post as fcom configured it', async () => {
            const { filters } = await loadPortal();

            expect(filters.fluent_com_composer_config(config, { content_type: 'text' })).toBe(config);
            expect(filters.fluent_com_composer_config(config, null)).toBe(config);
        });
    });

    it('opens a published event post but just goes back from a scheduled one', async () => {
        const { filters } = await loadPortal();
        const createShell = filters.fluent_com_portal_routes([spaceRoute()])
            .find(route => route.name === 'fe_create_event').component;
        const shell = { $router: { push: vi.fn(), back: vi.fn() } };

        createShell.methods.posted.call(shell, { status: 'published', slug: 'meetup' });
        createShell.methods.posted.call(shell, { status: 'scheduled', slug: 'meetup' });

        expect(shell.$router.push.mock.calls).toEqual([
            [{ name: 'single_feed', params: { feed_slug: 'meetup' } }]
        ]);
        expect(shell.$router.back).toHaveBeenCalledTimes(1);
    });

    describe('create page placement', () => {
        const createShell = async () => {
            const { filters } = await loadPortal();

            return filters.fluent_com_portal_routes([spaceRoute()]).find(route => route.name === 'fe_create_event').component;
        };

        const shellData = (shell, query, globalPosts) => {
            window.fluentEventFcomPortal = { ...window.fluentEventFcomPortal, global_posts: globalPosts };

            return shell.data.call({ $route: { query } });
        };

        it('starts in a space, with no space picked, when opened without one', async () => {
            const data = shellData(await createShell(), {}, true);

            expect(data.placement).toBe('space');
            expect(data.feed.space).toBe('');
        });

        it("starts in the space, picked in the composer, when opened from a space's menu", async () => {
            const data = shellData(await createShell(), { space: 'design' }, true);

            expect(data.placement).toBe('space');
            expect(data.feed.space).toBe('design');
        });

        it("starts in a space, with no space picked, when fcom's global posts are off", async () => {
            const data = shellData(await createShell(), {}, false);

            expect(data.placement).toBe('space');
            expect(data.feed.space).toBe('');
        });

        it('points the composer at the profile post or back at the space as the choice changes', async () => {
            const { placement } = (await createShell()).watch;
            const shell = { $route: { query: { space: 'design' } }, feed: { space: 'design' } };

            placement.call(shell, 'global');
            expect(shell.feed.space).toBe('__self__post__');

            placement.call(shell, 'space');
            expect(shell.feed.space).toBe('design');
        });

        it('starts the composer folded, as fcom shows it in the feed', async () => {
            expect((await createShell()).template).not.toContain('default-state');
        });

        it('opens a folded composer before posting, so an empty caption gets fcom\'s own error', async () => {
            const { submitPost } = (await createShell()).methods;
            const composer = { isStateActive: false, makeActive: vi.fn(), post: vi.fn(), $nextTick: (callback) => callback() };
            const onPosting = vi.fn();

            submitPost.call({ $refs: { composer } }, onPosting);

            expect(composer.makeActive).toHaveBeenCalled();
            expect(composer.post).toHaveBeenCalledTimes(1);
        });

        it('posts straight away from an open composer', async () => {
            const { submitPost } = (await createShell()).methods;
            const composer = { isStateActive: true, makeActive: vi.fn(), post: vi.fn() };

            submitPost.call({ $refs: { composer } }, vi.fn());

            expect(composer.makeActive).not.toHaveBeenCalled();
            expect(composer.post).toHaveBeenCalledTimes(1);
        });

        // The composer hides itself when its space prop names no real space, as the profile post doesn't.
        it("never hands the profile post to the composer's space prop, and hides its picker for community-wide", async () => {
            const template = (await createShell()).template;

            expect(template).not.toMatch(/:space=/);
            expect(template).toContain(`:disableSpace="placement === 'global'"`);
        });
    });

    // Mounts the real route shell, so the page goes through the same createApp() the portal uses.
    describe('page shell', () => {
        let wrapper;

        const mountEventsShell = async () => {
            const { filters } = await loadPortal();
            const shell = filters.fluent_com_portal_routes([{ path: '/', name: 'all_feeds' }])
                .find(route => route.name === 'fe_events').component;

            wrapper = mount(shell, { global: { mocks: { $route: { params: {} }, $router: { push: vi.fn() } } } });

            // The page and Vue load through dynamic imports (a first compile takes over a second), then the page app mounts.
            for (let i = 0; i < 1200 && !wrapper.vm.ready; i++) {
                await new Promise(resolve => setTimeout(resolve, 10));
            }
            await new Promise(resolve => setTimeout(resolve, 0));

            return wrapper;
        };

        beforeEach(() => {
            // A list request that never answers keeps the page loading.
            vi.stubGlobal('fetch', vi.fn(() => new Promise(() => {})));

            const warn = console.warn;
            vi.spyOn(console, 'warn').mockImplementation((message, ...rest) => {
                if (!String(message).includes('Failed to resolve directive: loading')) {
                    warn(message, ...rest);
                }
            });
        });

        afterEach(() => {
            wrapper && wrapper.unmount();
            vi.unstubAllGlobals();
            vi.restoreAllMocks();
            // remount() sets the week start on the shared test dayjs; the other tests expect Sunday.
            dayjs.updateLocale('en', { weekStart: 0 });
        });

        it('starts the page app and fcom\'s dayjs on the WordPress "Week Starts On" day', async () => {
            window.fluentEventFcomPortal.start_of_week = 1;
            // fcom's window.dayjs is a separate copy in the portal; here both are the test's dayjs.
            const updateLocale = vi.spyOn(window.dayjs, 'updateLocale');

            await mountEventsShell();

            expect(updateLocale.mock.calls).toEqual([['en', { weekStart: 1 }], ['en', { weekStart: 1 }]]);
            expect(dayjs('2026-10-28').startOf('week').format('YYYY-MM-DD')).toBe('2026-10-26');
        }, 15000);

        it('KNOWN ISSUE #173 — shows a loading mask while the list loads', async (context) => {
            await mountEventsShell();

            expect(wrapper.vm.ready).toBe(true);
            expect(wrapper.find('.fe-events-page__list').exists()).toBe(true);

            // #173: the page app never registers ElLoading, so v-loading is inert.
            await expectKnownIssue('#173', context, () => {
                expect(wrapper.find('.el-loading-mask').exists()).toBe(true);
            });
        }, 15000);
    });
});
