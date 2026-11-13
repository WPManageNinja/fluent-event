import EventCard from './EventCard';
import { t, portalVars, uploadMedia } from './utils';

/*
 * Plugs FluentEvent into the FluentCommunity portal through fcom's JS hooks.
 * fcom loads it after its start.js and before its app (module_scripts), so the hooks are there and the router is not built yet.
 */

// Lazy, so our own Vue, Element Plus and the pages download only when an events page opens, not on every portal view.
const pages = {
    events: () => import('./app/EventsPage.vue'),
    space_events: () => import('./app/EventsPage.vue'),
    create_event: () => import('./app/CreateEventPage.vue'),
    event_view: () => import('./app/EventViewPage.vue'),
    // The same page outside any space, where no space layout frames it.
    global_event_view: () => import('./app/EventViewPage.vue'),
};

// A route shell on fcom's Vue; the page itself is a separate app loaded on first visit.
function pageShell(page) {
    return {
        name: 'FluentEventPage',
        // fcom hands space routes space_profile, search and the like; the shell reads $route instead.
        inheritAttrs: false,
        data() {
            return { unmount: null, gone: false, ready: false };
        },
        watch: {
            '$route.params.space'() {
                this.remount();
            },
            '$route.params.event_slug'() {
                this.remount();
            },
        },
        mounted() {
            this.remount();
        },
        beforeUnmount() {
            this.gone = true;
            this.unmount && this.unmount();
        },
        methods: {
            pageProps() {
                return {
                    spaceSlug: this.$route.params.space,
                    eventSlug: this.$route.params.event_slug,
                    router: this.$router,
                };
            },
            async remount() {
                const [{ createApp }, { default: Page }, { applyWeekStart }, { vLoading }] = await Promise.all([
                    import('vue'),
                    pages[page](),
                    import('@/utils/date'),
                    import('element-plus'),
                ]);

                if (this.gone) {
                    return;
                }

                this.unmount && this.unmount();

                // Our Element Plus and fcom's window.dayjs (the page's week maths) both start on the WordPress "Week Starts On" day.
                applyWeekStart(portalVars().start_of_week, window.dayjs);

                // Its own Vue app: fcom exposes neither its Vue nor Element Plus to add-ons.
                const app = createApp(Page, this.pageProps());
                app.config.globalProperties.$t = t;
                // The pages' v-loading spinners (list, calendar, cover upload, editor) need it registered here.
                app.directive('loading', vLoading);
                app.mount(this.$refs.mount);

                this.unmount = () => app.unmount();
                this.ready = true;
            },
        },
        template: `<div class="fe-fcom-portal-page fe-fcom-portal-page--${page}"><div ref="mount"></div></div>`,
    };
}

// The event page with fcom's comment box for its post, teleported into the page's Comments card once the page has the post.
function eventViewShell(page) {
    const base = pageShell(page);

    return {
        extends: base,
        data() {
            return { feed: null };
        },
        methods: {
            pageProps() {
                return {
                    ...base.methods.pageProps.call(this),
                    onFeed: (feed) => {
                        this.feed = feed;
                    },
                };
            },
            // The box leaves with the page it sits in; the next page hands its own post.
            remount() {
                this.feed = null;
                return base.methods.remount.call(this);
            },
        },
        template: `
            <div class="fe-fcom-portal-page fe-fcom-portal-page--${page}">
                <div ref="mount"></div>
                <teleport v-if="feed" to="#fe-event-view-comments">
                    <comment-box :feed="feed"/>
                </teleport>
            </div>
        `,
    };
}

// fcom's own composer writes and submits the post; our page only fills in the event it carries.
// The composer lives on fcom's Vue, so it is teleported into the page's Feed post section once the page is in.
// fcom's "On my profile" space value: a post outside any space.
const PROFILE_POST = '__self__post__';

const createEventShell = {
    extends: pageShell('create_event'),
    data() {
        return {
            onPosting: null,
            placement: 'space',
            // The description's markdown, from fcom's editor; it rides in the composer's event, or in an edit's PATCH.
            description: '',
            // fcom's editor reads its v-model only when it mounts, so an edit waits for the event's markdown.
            editorReady: !this.$route.params.event_id,
            // The space picked on the page; kept across a switch to community-wide and back.
            space: this.$route.query.space || '',
            feed: {
                title: '',
                message: '',
                type: 'text',
                content_type: 'event',
                event: {},
                topic_ids: [],
                send_announcement_email: 'no',
                status: 'published',
                // The composer shares this object and posts to feed.space; its own picker is hidden, so the
                // page sets it. Community-wide sets PROFILE_POST here, never through the composer's space
                // prop: the composer hides itself for a space it can't find, and PROFILE_POST isn't one.
                space: this.$route.query.space || '',
            },
        };
    },
    watch: {
        placement(placement) {
            this.feed.space = placement === 'global' ? PROFILE_POST : this.space;
        },
        description(markdown) {
            this.feed.event = { ...this.feed.event, description_md: markdown };
        },
    },
    methods: {
        t,
        pageProps() {
            // Editing changes the event only; its space and post text are edited on fcom's post.
            if (this.$route.params.event_id) {
                return {
                    router: this.$router,
                    eventId: this.$route.params.event_id,
                    onLoaded: (event) => {
                        this.description = (event.meta || {}).description_md || '';
                        this.editorReady = true;
                    },
                    getDescription: () => this.description,
                };
            }

            return {
                router: this.$router,
                onChange: (event) => {
                    this.feed.event = { ...event, description_md: this.description };
                },
                submitPost: (onPosting) => this.submitPost(onPosting),
                globalPosts: !!portalVars().global_posts,
                onPlacement: (placement) => {
                    this.placement = placement;
                },
                // fcom's own list for its composer picker: discussion spaces, keyed by slug, with the member's permissions.
                spaces: Object.values(((this.appVars || {}).auth || {}).spaces || {})
                    .filter((space) => space.permissions && space.permissions.can_create_post)
                    .map((space) => ({ slug: space.slug, title: space.title, emoji: (space.settings || {}).emoji || '' })),
                initialSpace: this.space,
                onSpace: (space) => {
                    this.space = space;
                    this.feed.space = space;
                },
            };
        },
        // Images go to the media library, like the cover; fcom's editor just places the URL.
        async uploadDescriptionImage(file) {
            try {
                return await uploadMedia(file);
            } catch (error) {
                this.$handleError(error);
                throw error;
            }
        },
        // Our Create button submits fcom's composer, which reports back while it posts.
        submitPost(onPosting) {
            const composer = this.$refs.composer;
            this.onPosting = onPosting;

            // post() does nothing while the composer is folded, and an event post needs no caption, so open it and post.
            if (!composer.isStateActive) {
                composer.makeActive();
                composer.$nextTick(() => composer.post());
                return;
            }

            composer.post();
        },
        watchPosting() {
            this.$refs.composer.$watch('posting', (posting) => this.onPosting && this.onPosting(posting));
        },
        // A scheduled or held post has no page to open yet, so the admin goes back where they came from.
        posted(feed) {
            if (feed.status === 'published') {
                this.$router.push({ name: 'single_feed', params: { feed_slug: feed.slug } });
                return;
            }

            this.$router.back();
        },
    },
    template: `
        <div class="fe-fcom-portal-page fe-fcom-portal-page--create_event">
            <div ref="mount"></div>
            <teleport v-if="ready && !$route.params.event_id" to="#fe-create-event-composer">
                <create-status-form
                    ref="composer"
                    defaultState="active"
                    :disableSpace="true"
                    :hideSubmit="true"
                    :editing_feed="feed"
                    @vue:mounted="watchPosting"
                    @added="posted"
                    @scheduled="posted"
                />
            </teleport>
            <!-- fcom's own Milkdown: a second copy on this page would clash with the composer's over the custom elements both define. -->
            <teleport v-if="ready && editorReady" to="#fe-create-event-description">
                <crepe-editor
                    v-model="description"
                    :placeholder="t('Describe the event, or type / to add images, lists and more')"
                    :disable_mention="true"
                    :onInlineUpload="uploadDescriptionImage"
                />
            </teleport>
        </div>
    `,
};

function register() {
    const { addFilter } = window.FluentCommunityUtil.hooks;

    addFilter('fluent_com_portal_routes', 'fluent-event', (routes) => {
        // Built server-side from fcom's events slug, through the fluent_event/community/route_paths filter.
        const paths = portalVars().route_paths;

        routes.push({
            path: '/' + paths.events,
            name: 'fe_events',
            component: pageShell('events'),
            meta: { active_menu: 'fe_events' },
        });

        // ?space=slug starts on that space; without it, on community-wide.
        routes.push({
            path: '/' + paths.create_event,
            name: 'fe_create_event',
            component: createEventShell,
            meta: { active_menu: 'fe_events' },
        });

        routes.push({
            path: '/' + paths.edit_event,
            name: 'fe_edit_event',
            component: createEventShell,
            meta: { active_menu: 'fe_events' },
        });

        // A community-wide event; the space one below sits under its space's header and tabs.
        routes.push({
            path: '/' + paths.event_view,
            name: 'fe_global_event_view',
            component: eventViewShell('global_event_view'),
            meta: { active_menu: 'fe_events' },
        });

        const spaceRoute = routes.find((route) => route.path === '/space/:space/');

        if (spaceRoute && spaceRoute.children) {
            spaceRoute.children.push({
                path: paths.space_events,
                name: 'fe_space_events',
                component: pageShell('space_events'),
                props: true,
                meta: { in_space: true, show_sidebar: false },
            });

            spaceRoute.children.push({
                path: paths.space_event_view,
                name: 'fe_event_view',
                component: eventViewShell('event_view'),
                props: true,
                meta: { in_space: true, show_sidebar: false },
            });
        }

        return routes;
    });

    addFilter('fluent_com_feed_content_components', 'fluent-event', (components) => {
        components.event = EventCard;
        return components;
    });

    addFilter('fluent_com_space_actions', 'fluent-event', (actions, space) => {
        if (portalVars().can_create_events && space.type === 'community' && space.permissions && space.permissions.can_create_post) {
            actions.push({
                key: 'fe_create_event',
                title: t('Create new event'),
                route: { name: 'fe_create_event', query: { space: space.slug } },
            });
        }

        return actions;
    });

    addFilter('fluent_com_feed_actions', 'fluent-event', (actions) => {
        if (portalVars().can_create_events) {
            actions.push({
                key: 'fe_create_event',
                title: t('Create new event'),
                route: { name: 'fe_create_event' },
            });
        }

        return actions;
    });

    // Event posts carry the event instead of attachments; images inside the message stay allowed.
    // "On my profile" is the create page's Community-wide choice, so the space picker lists spaces only.
    addFilter('fluent_com_composer_config', 'fluent-event', (config, feed) => {
        if (!feed || feed.content_type !== 'event') {
            return config;
        }

        return {
            ...config,
            surveyApp: false,
            documentsApp: false,
            videoApp: false,
            mediaApp: false,
            giphyApp: false,
            selfPost: false,
        };
    });
}

register();
