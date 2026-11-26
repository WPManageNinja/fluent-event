import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import dayjs from 'dayjs';

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
    delete window.fluentEventPortalRegistered;
});

describe('fcom-portal entry', () => {
    it("registers on fcom's hooks as it loads", async () => {
        const { util } = await loadPortal();

        expect(util.hooks.addFilter.mock.calls.map(([name, namespace]) => [name, namespace])).toEqual([
            ['fluent_com_portal_routes', 'fluent-event'],
            ['fluent_com_feed_content_components', 'fluent-event'],
            ['fluent_com_space_sidebar_widgets', 'fluent-event'],
            ['fluent_com_space_actions', 'fluent-event'],
            ['fluent_com_feed_actions', 'fluent-event'],
            ['fluent_com_composer_config', 'fluent-event']
        ]);
    });

    it('registers once when a lazy page loads the entry again without its ?ver=, so menus get no second Create new event', async () => {
        const { util } = await loadPortal();

        vi.resetModules();
        await import('../../../resources/fcom-portal/fcom-portal.js');

        expect(util.hooks.addFilter).toHaveBeenCalledTimes(6);
    });

    describe('routes', () => {
        it('adds the community events page, the create and edit pages and the community-wide event page', async () => {
            const { filters } = await loadPortal();

            const routes = filters.fluent_com_portal_routes([spaceRoute()]);

            expect(routes.slice(1).map(({ path, name, meta }) => ({ path, name, meta }))).toEqual([
                { path: '/events', name: 'fe_events', meta: { active_menu: 'fe_events' } },
                { path: '/events/create', name: 'fe_create_event', meta: { active_menu: 'fe_events' } },
                { path: '/events/:event_slug/edit', name: 'fe_edit_event', meta: { active_menu: 'fe_events' } },
                { path: '/events/:event_slug', name: 'fe_global_event_view', meta: { active_menu: 'fe_events' } }
            ]);
        });

        it('edits an event on the create page', async () => {
            const { filters } = await loadPortal();
            const routes = filters.fluent_com_portal_routes([spaceRoute()]);

            expect(routes.find(r => r.name === 'fe_edit_event').component).toBe(routes.find(r => r.name === 'fe_create_event').component);
        });

        it("shows fcom's Not Found on the create page for anyone who cannot create events, not the event view of slug 'create'", async () => {
            window.fluentEventFcomPortal.can_create_events = false;
            const { filters } = await loadPortal();

            const routes = filters.fluent_com_portal_routes([spaceRoute()]);

            expect(routes.slice(1).map(({ path, name }) => ({ path, name }))).toEqual([
                { path: '/events', name: 'fe_events' },
                { path: '/events/create', name: undefined },
                { path: '/events/:event_slug', name: 'fe_global_event_view' },
            ]);

            const NotFound = { template: '<p class="not-found">Not Found</p>' };
            const wrapper = mount(routes[2].component, {
                global: { mocks: { $router: { getRoutes: () => [{ name: 'not-found', components: { default: NotFound } }] } } },
            });

            expect(wrapper.find('.not-found').exists()).toBe(true);
        });

        it('takes every path from the server, so a renamed slug moves all of them', async () => {
            window.fluentEventFcomPortal.route_paths = {
                events: 'meetups',
                create_event: 'meetups/create',
                edit_event: 'meetups/:event_slug/edit',
                event_view: 'meetups/:event_slug',
                space_events: 'meetups',
                space_event_view: 'meetups/:event_slug'
            };
            const { filters } = await loadPortal();

            const routes = filters.fluent_com_portal_routes([spaceRoute()]);

            expect(routes.slice(1).map(route => route.path)).toEqual(['/meetups', '/meetups/create', '/meetups/:event_slug/edit', '/meetups/:event_slug']);
            expect(routes[0].children.slice(1).map(route => route.path)).toEqual(['meetups', 'meetups/:event_slug']);
        });

        it("adds the Events tab and the single event page inside fcom's space route", async () => {
            const { filters } = await loadPortal();

            const [space] = filters.fluent_com_portal_routes([spaceRoute()]);

            expect(space.children.map(({ path, name, meta }) => ({ path, name, meta }))).toEqual([
                expect.objectContaining({ name: 'space_feeds' }),
                { path: 'events', name: 'fe_space_events', meta: { in_space: true, show_sidebar: false } },
                { path: 'events/:event_slug', name: 'fe_event_view', meta: { in_space: true, show_sidebar: false, owns_title: true } }
            ]);
        });

        // fcom constrains the slug in newer versions; the settings route sits beside it and must not take our pages.
        it('finds the space route with a constrained slug too, and not the space settings route', async () => {
            const { filters } = await loadPortal();
            const settings = { path: '/space/:space([a-zA-Z0-9_-]+)/settings', children: [] };
            const space = { path: '/space/:space([a-zA-Z0-9_-]+)/', children: [] };

            filters.fluent_com_portal_routes([settings, space]);

            expect(settings.children).toEqual([]);
            expect(space.children.map(child => child.name)).toEqual(['fe_space_events', 'fe_event_view']);
        });

        it('frames the community-wide event page itself, since no space layout does', async () => {
            const { filters } = await loadPortal();
            const route = filters.fluent_com_portal_routes([spaceRoute()]).find(r => r.name === 'fe_global_event_view');

            expect(route.component.template).toContain('fe-fcom-portal-page--global_event_view');
        });

        it('leaves spaces alone when fcom has no space route', async () => {
            const { filters } = await loadPortal();

            const routes = filters.fluent_com_portal_routes([{ path: '/', name: 'all_feeds' }]);

            expect(routes.map(route => route.name)).toEqual(['all_feeds', 'fe_events', 'fe_create_event', 'fe_edit_event', 'fe_global_event_view']);
        });
    });

    it('renders event posts with the event card', async () => {
        const { filters } = await loadPortal();

        const components = filters.fluent_com_feed_content_components({ text: {} });

        expect(components.event.name).toBe('FluentEventCard');
        expect(components.text).toEqual({});
    });

    describe('space sidebar widgets', () => {
        const space = (show) => ({ slug: 'design', settings: show === undefined ? {} : { show_upcoming_events: show } });

        it('adds Upcoming Events after other widgets when the space turns it on', async () => {
            const { filters } = await loadPortal();
            const other = { key: 'other', component: {} };

            const widgets = filters.fluent_com_space_sidebar_widgets([other], space('yes'));

            expect(widgets.map((widget) => widget.key)).toEqual(['other', 'fe_upcoming_events']);
            expect(widgets[1].component.name).toBe('FluentEventSpaceEventsWidget');
        });

        it('adds nothing while the space setting is off or missing', async () => {
            const { filters } = await loadPortal();

            expect(filters.fluent_com_space_sidebar_widgets([], space('no'))).toEqual([]);
            expect(filters.fluent_com_space_sidebar_widgets([], space())).toEqual([]);
        });
    });

    describe('space actions', () => {
        const space =(overrides = {}) => ({ slug: 'design', type: 'community', permissions: { can_create_post: true }, ...overrides });

        it('offers Create Event to a member who can post in a community space', async () => {
            const { filters } = await loadPortal();

            expect(filters.fluent_com_space_actions([], space())).toEqual([{
                key: 'fe_create_event',
                title: 'Create Event',
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
        it('offers Create Event, without a space, to an event creator', async () => {
            const { filters } = await loadPortal();

            expect(filters.fluent_com_feed_actions([])).toEqual([{
                key: 'fe_create_event',
                title: 'Create Event',
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

            return shell.data.call({ $route: { params: {}, query } });
        };

        // fcom's composer already runs fcom's Milkdown here; a second copy would clash over their custom elements.
        it("teleports fcom's markdown editor into the page, with mentions off and uploads going to the media library", async () => {
            const { template } = await createShell();

            expect(template).toMatch(/<teleport v-if="ready && editorReady" to="#fe-create-event-description">\s*<crepe-editor/);
            expect(template).toContain('v-model="description"');
            expect(template).toContain(':disable_mention="true"');
            expect(template).toContain(':onInlineUpload="uploadDescriptionImage"');
        });

        it("carries the editor's markdown in the composer's event, through the page's later changes too", async () => {
            const shell = await createShell();
            const vm = { ...shellData(shell, {}, true), $route: { params: {}, query: {} }, $router: {}, appVars: {} };

            shell.watch.description.call(vm, '**Bring** a tripod');
            expect(vm.feed.event.description_md).toBe('**Bring** a tripod');

            vm.description = '**Bring** a tripod';
            shell.methods.pageProps.call(vm).onChange({ title: 'Night walk' });
            expect(vm.feed.event).toEqual({ title: 'Night walk', description_md: '**Bring** a tripod' });
        });

        it("uploads the editor's images to the media library, and says why one is refused", async () => {
            const { methods } = await createShell();
            const $handleError = vi.fn();
            vi.stubGlobal('fetch', vi.fn()
                .mockResolvedValueOnce(new Response(JSON.stringify({ id: 9, source_url: 'https://example.test/uploads/walk.png' })))
                .mockResolvedValueOnce(new Response(JSON.stringify({ message: 'Sorry, this file type is not permitted.' }), { status: 500 })));

            await expect(methods.uploadDescriptionImage.call({ $handleError }, new File(['x'], 'walk.png'))).resolves.toBe('https://example.test/uploads/walk.png');
            expect(fetch.mock.calls[0][0]).toBe('https://example.test/wp-json/wp/v2/media');

            await expect(methods.uploadDescriptionImage.call({ $handleError }, new File(['x'], 'a.exe'))).rejects.toMatchObject({ message: 'Sorry, this file type is not permitted.' });
            expect($handleError).toHaveBeenCalledWith({ message: 'Sorry, this file type is not permitted.' });
            vi.unstubAllGlobals();
        });

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
            const shell = { space: 'design', feed: { space: 'design' } };

            placement.call(shell, 'global');
            expect(shell.feed.space).toBe('__self__post__');

            placement.call(shell, 'space');
            expect(shell.feed.space).toBe('design');
        });

        // fcom's composer and editor read their content only when they mount, so a clone holds both until its source loads.
        it("copies a clone's post into the composer, then mounts the composer and the editor", async () => {
            const shell = await createShell();
            const $route = { params: {}, query: { clone: 'night-walk' } };
            const vm = { ...shellData(shell, $route.query, true), $route, $router: {}, appVars: {} };
            const props = shell.methods.pageProps.call(vm);

            expect(props.cloneSourceSlug).toBe('night-walk');
            expect(vm.editorReady).toBe(false);
            expect(vm.composerReady).toBe(false);
            expect(shell.template).toContain('<teleport v-if="ready && composerReady && !$route.params.event_slug" to="#fe-create-event-composer">');

            props.onLoaded(
                { meta: { description_md: '**Bring** a torch' } },
                { title: 'Walk', message: 'Join us', type: 'text', topic_ids: [4], send_announcement_email: 'yes' }
            );

            expect(vm.description).toBe('**Bring** a torch');
            expect(vm.feed).toMatchObject({ title: 'Walk', message: 'Join us', type: 'text', topic_ids: [4], send_announcement_email: 'yes', content_type: 'event' });
            expect(vm.editorReady).toBe(true);
            expect(vm.composerReady).toBe(true);
        });

        it('mounts the composer and the editor at once without a clone', async () => {
            const data = shellData(await createShell(), {}, true);

            expect(data.editorReady).toBe(true);
            expect(data.composerReady).toBe(true);
        });

        it('opens the composer from the start, since the page is there to write a post', async () => {
            expect((await createShell()).template).toContain('defaultState="active"');
        });

        it("hands the page the spaces the member can post in, and points the composer at the one picked", async () => {
            const shell = (await createShell());
            const vm = {
                ...shell.data.call({ $route: { params: {}, query: {} } }),
                $route: { params: {}, query: {} },
                $router: {},
                appVars: { auth: { spaces: {
                    design: { slug: 'design', title: 'Design Team', settings: { emoji: '🎨' }, permissions: { can_create_post: true } },
                    news: { slug: 'news', title: 'News', settings: {}, permissions: { can_create_post: false } },
                } } },
            };
            const props = shell.methods.pageProps.call(vm);

            expect(props.spaces).toEqual([{ slug: 'design', title: 'Design Team', emoji: '🎨' }]);

            props.onSpace('design');
            expect(vm.feed.space).toBe('design');
            expect(vm.space).toBe('design');
        });

        it('opens a folded composer before posting, since post() ignores a folded one', async () => {
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
        it("never hands the profile post to the composer's space prop, and always hides its picker, since the page has its own", async () => {
            const template = (await createShell()).template;

            expect(template).not.toMatch(/:space=/);
            expect(template).toContain(':disableSpace="true"');
        });
    });

    // Mounts the real route shell, so the page goes through the same createApp() the portal uses.
    describe('event page comments', () => {
        const viewShells = async () => {
            const { filters } = await loadPortal();
            const routes = filters.fluent_com_portal_routes([spaceRoute()]);

            return [routes.find(r => r.name === 'fe_global_event_view'), routes[0].children.find(r => r.name === 'fe_event_view')]
                .map(route => route.component);
        };

        it("teleports fcom's comment box for the post the page hands over into its Comments card", async () => {
            for (const shell of await viewShells()) {
                expect(shell.template).toMatch(/<teleport v-if="feed" to="#fe-event-view-comments">\s*<comment-box :feed="feed"\/>/);

                const vm = { ...shell.data.call({}), $route: { params: { event_slug: 'meetup' } }, $router: {} };
                const props = shell.methods.pageProps.call(vm);
                const feed = { id: 7 };

                expect(props.eventSlug).toBe('meetup');
                props.onFeed(feed);
                expect(vm.feed).toBe(feed);
            }
        });
    });

    describe('page shell', () => {
        let wrapper;

        const mountShell = async (name = 'fe_events', $route = { params: {} }, global = {}) => {
            const { filters } = await loadPortal();
            const shell = filters.fluent_com_portal_routes([{ path: '/', name: 'all_feeds' }])
                .find(route => route.name === name).component;

            wrapper = mount(shell, { global: { mocks: { $route, $router: { push: vi.fn() } }, ...global } });

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

            await mountShell();

            expect(updateLocale.mock.calls).toEqual([['en', { weekStart: 1 }], ['en', { weekStart: 1 }]]);
            expect(dayjs('2026-10-28').startOf('week').format('YYYY-MM-DD')).toBe('2026-10-26');
        }, 15000);

        it('shows a loading mask while the list loads', async () => {
            await mountShell();

            expect(wrapper.vm.ready).toBe(true);
            expect(wrapper.find('.fe-events-page__list').exists()).toBe(true);
            expect(wrapper.find('.el-loading-mask').exists()).toBe(true);
        }, 15000);

        // fcom's sticky page header rule is `.fcom_sticky_header .fhr_content_layout_header`.
        it("puts fcom's route classes on the page, but no other attribute fcom hands the route", async () => {
            const { filters } = await loadPortal();
            const routes = filters.fluent_com_portal_routes([spaceRoute()]);
            const shells = [...routes.slice(1), ...routes[0].children.slice(1)].map(route => route.component);

            for (const shell of new Set(shells)) {
                wrapper = mount(shell, {
                    attrs: { class: 'fcom_sticky_header', space_profile: 'design' },
                    global: { mocks: { $route: { params: {}, query: {} }, $router: { push: vi.fn() } }, stubs: { teleport: true, CreateStatusForm: true, CrepeEditor: true, CommentBox: true } }
                });

                expect(wrapper.classes()).toEqual(expect.arrayContaining(['fe-fcom-portal-page', 'fcom_sticky_header']));
                expect(wrapper.attributes('space_profile')).toBeUndefined();
                wrapper.unmount();
                wrapper = null;
            }
        });

        // fcom's editor reads its v-model only when it mounts; the post's composer has no place on an edit.
        it("mounts no composer when editing, and fcom's editor only once the event brings its markdown", async () => {
            let respond;
            fetch.mockImplementationOnce(() => new Promise((resolve) => { respond = resolve; }));
            const components = {
                CreateStatusForm: { template: '<div class="composer-stub"></div>' },
                CrepeEditor: { props: ['modelValue'], template: '<div class="editor-stub">{{ modelValue }}</div>' }
            };

            // The page focuses its title once loaded, and the title sits in a form item.
            // ElInputNumber reads its inner ElInput's element, which the stub doesn't have.
            const stubs = { ElFormItem: { template: '<div><slot /></div>' }, ElInput: { template: '<input />', methods: { focus() {} } }, ElInputNumber: true };

            await mountShell('fe_edit_event', { params: { event_slug: 'night-walk' }, query: {} }, { components, stubs });

            expect(fetch.mock.calls[0][0]).toBe('https://example.test/wp-json/fluent-event/v2/events/by-slug?slug=night-walk');
            expect(wrapper.find('.editor-stub').exists()).toBe(false);

            respond(new Response(JSON.stringify({ event: {
                title: 'Night walk',
                start_at_local: '2026-11-20 18:30:00',
                end_at_local: '2026-11-20 20:00:00',
                meta: { description_md: '**Bring** a torch' }
            } })));
            for (let i = 0; i < 5; i++) {
                await new Promise(resolve => setTimeout(resolve, 0));
            }

            expect(wrapper.find('.editor-stub').text()).toBe('**Bring** a torch');
            expect(wrapper.find('.composer-stub').exists()).toBe(false);
        }, 15000);
    });
});
