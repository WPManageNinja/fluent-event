import EventCard from './EventCard';
import { t, portalVars } from './utils';

/*
 * Plugs FluentEvent into the FluentCommunity portal through fcom's JS hooks.
 * Printed before fcom's own modules, so it waits for FluentCommunityUtil.
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
            return { unmount: null, gone: false };
        },
        watch: {
            '$route.params.space'() {
                this.remount();
            },
            '$route.params.feed_slug'() {
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
            async remount() {
                const [{ createApp }, { default: Page }] = await Promise.all([import('vue'), pages[page]()]);

                if (this.gone) {
                    return;
                }

                this.unmount && this.unmount();

                // Its own Vue app: fcom exposes neither its Vue nor Element Plus to add-ons.
                const app = createApp(Page, {
                    spaceSlug: this.$route.params.space,
                    feedSlug: this.$route.params.feed_slug,
                    router: this.$router,
                });
                app.config.globalProperties.$t = t;
                app.mount(this.$refs.mount);

                this.unmount = () => app.unmount();
            },
        },
        template: `<div class="fe-fcom-portal-page fe-fcom-portal-page--${page}"><div ref="mount"></div></div>`,
    };
}

function register() {
    const { addFilter } = window.FluentCommunityUtil.hooks;

    addFilter('fluent_com_portal_routes', 'fluent-event', (routes) => {
        routes.push({
            path: '/events',
            name: 'fe_events',
            component: pageShell('events'),
            meta: { active_menu: 'fe_events' },
        });

        // Beside fcom's /post/:feed_slug, but outside its feed layout: the event alone.
        routes.push({
            path: '/post/:feed_slug/event',
            name: 'fe_event_view',
            component: pageShell('event_view'),
            meta: { active_menu: 'fe_events' },
        });

        const spaceRoute = routes.find((route) => route.path === '/space/:space/');

        if (spaceRoute && spaceRoute.children) {
            spaceRoute.children.push(
                {
                    path: 'events',
                    name: 'fe_space_events',
                    component: pageShell('space_events'),
                    props: true,
                    meta: { in_space: true, show_sidebar: false },
                },
                {
                    path: 'create-event',
                    name: 'fe_space_create_event',
                    component: pageShell('create_event'),
                    props: true,
                    meta: { in_space: true, show_sidebar: false },
                }
            );
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
                route: { name: 'fe_space_create_event', params: { space: space.slug } },
            });
        }

        return actions;
    });

    // Event posts carry the event instead of media, so the post editor offers no attachments.
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
            disableUpload: true,
        };
    });
}

if (window.FluentCommunityUtil) {
    register();
} else {
    document.addEventListener('fluentCommunityUtilReady', register, { once: true });
}
