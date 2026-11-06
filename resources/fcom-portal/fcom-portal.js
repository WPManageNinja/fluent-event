import EventCard from './EventCard';
import { t, portalVars } from './utils';

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
                const [{ createApp }, { default: Page }, { applyWeekStart }] = await Promise.all([
                    import('vue'),
                    pages[page](),
                    import('@/utils/date'),
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
                app.mount(this.$refs.mount);

                this.unmount = () => app.unmount();
                this.ready = true;
            },
        },
        template: `<div class="fe-fcom-portal-page fe-fcom-portal-page--${page}"><div ref="mount"></div></div>`,
    };
}

// fcom's own composer writes and submits the post; our page only fills in the event it carries.
// The composer lives on fcom's Vue, so it is the page's last section from out here, shown once the page is in.
const createEventShell = {
    extends: pageShell('create_event'),
    data() {
        return {
            onPosting: null,
            space: '',
            feed: {
                title: '',
                message: '',
                type: 'text',
                content_type: 'event',
                event: {},
                topic_ids: [],
                send_announcement_email: 'no',
                status: 'published',
            },
        };
    },
    methods: {
        t,
        pageProps() {
            return {
                router: this.$router,
                onChange: (event) => {
                    this.feed.event = event;
                },
                submitPost: (onPosting) => this.submitPost(onPosting),
            };
        },
        // Our header button submits fcom's composer, which reports back while it posts.
        submitPost(onPosting) {
            this.onPosting = onPosting;
            this.space = this.$refs.composer.currentSpaceSlug;
            this.$refs.composer.post();
        },
        watchPosting() {
            this.$refs.composer.$watch('posting', (posting) => this.onPosting && this.onPosting(posting));
        },
        // A scheduled or held post has no page to open yet.
        posted(feed) {
            const route = feed.status === 'published'
                ? { name: 'single_feed', params: { feed_slug: feed.slug } }
                : { name: 'fe_space_events', params: { space: this.space } };

            this.$router.push(route);
        },
    },
    template: `
        <div class="fe-fcom-portal-page fe-fcom-portal-page--create_event">
            <div ref="mount"></div>
            <section v-if="ready" class="fe-create-event-post">
                <div>
                    <h2 class="fe-create-event-post__title">{{ t('Feed post') }}</h2>
                    <p class="fe-create-event-post__hint">{{ t('What members see in the space feed, above the event.') }}</p>
                </div>
                <div class="fe-create-event-post__composer">
                    <create-status-form
                        ref="composer"
                        default-state="active"
                        :space="$route.query.space || ''"
                        :hideSubmit="true"
                        :editing_feed="feed"
                        @vue:mounted="watchPosting"
                        @added="posted"
                        @scheduled="posted"
                    />
                </div>
            </section>
            <div id="fe-create-event-actions"></div>
        </div>
    `,
};

function register() {
    const { addFilter } = window.FluentCommunityUtil.hooks;

    addFilter('fluent_com_portal_routes', 'fluent-event', (routes) => {
        routes.push({
            path: '/events',
            name: 'fe_events',
            component: pageShell('events'),
            meta: { active_menu: 'fe_events' },
        });

        // ?space=slug posts into that space; without it, the composer asks which space.
        routes.push({
            path: '/create-new-event',
            name: 'fe_create_event',
            component: createEventShell,
            meta: { active_menu: 'fe_events' },
        });

        const spaceRoute = routes.find((route) => route.path === '/space/:space/');

        if (spaceRoute && spaceRoute.children) {
            spaceRoute.children.push({
                path: 'events',
                name: 'fe_space_events',
                component: pageShell('space_events'),
                props: true,
                meta: { in_space: true, show_sidebar: false },
            });

            // Beside fcom's space/:space/post/:feed_slug: one event, under its space's header and tabs.
            spaceRoute.children.push({
                path: 'event/:event_slug',
                name: 'fe_event_view',
                component: pageShell('event_view'),
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
    // They also need a discussion space, so "On my profile" is not offered.
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
