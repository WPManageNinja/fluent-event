import EventCard from './EventCard';
import { t, portalVars } from './utils';

/*
 * Plugs FluentEvent into the FluentCommunity portal through fcom's JS hooks.
 * Printed before fcom's own modules, so it waits for FluentCommunityUtil.
 */

// A route shell on fcom's Vue; the page itself is a separate app loaded on first visit.
function pageShell(page) {
    return {
        name: 'FluentEventPage',
        props: ['space_profile'],
        data() {
            return { unmount: null, gone: false };
        },
        watch: {
            '$route.params.space'() {
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
                const { mountPage } = await import('./app/fcom-portal-app.js');

                if (this.gone) {
                    return;
                }

                this.unmount && this.unmount();
                this.unmount = mountPage(this.$refs.mount, {
                    page,
                    spaceSlug: this.$route.params.space || '',
                    spaceTitle: (this.space_profile && this.space_profile.title) || '',
                    router: this.$router,
                });
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
