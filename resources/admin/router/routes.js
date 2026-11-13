export default [
    {
        path: '/',
        name: 'dashboard',
        component: () => import('@/modules/dashboard/index.vue'),
        meta: { active_menu: 'dashboard' },
    },
    {
        path: '/calendars',
        name: 'calendars',
        component: () => import('@/modules/calendars/index.vue'),
        meta: {
            active_menu: 'calendars',
            middleware: 'permission',
            permissions: ['read_calendars', 'write_calendars'],
            match_type: 'any',
        },
    },
    {
        path: '/calendars/new',
        name: 'calendar_create',
        component: () => import('@/modules/calendars/CreateOrUpdateCalendar.vue'),
        meta: {
            active_menu: 'calendars',
            middleware: 'permission',
            permissions: ['write_calendars'],
        },
    },
    {
        path: '/calendars/:id(\\d+)/edit',
        name: 'calendar_edit',
        component: () => import('@/modules/calendars/CreateOrUpdateCalendar.vue'),
        meta: {
            active_menu: 'calendars',
            middleware: 'permission',
            permissions: ['write_calendars'],
        },
    },
    {
        path: '/calendars/:id(\\d+)',
        name: 'calendar_detail',
        component: () => import('@/modules/calendars/detail.vue'),
        meta: {
            active_menu: 'calendars',
            middleware: 'permission',
            permissions: ['read_calendars', 'write_calendars'],
            match_type: 'any',
        },
    },
    {
        path: '/events',
        name: 'events',
        component: () => import('@/modules/events/index.vue'),
        meta: {
            active_menu: 'events',
            middleware: 'permission',
            permissions: ['read_events', 'write_events'],
            match_type: 'any',
        },
    },
    {
        path: '/events/new',
        name: 'event_create',
        component: () => import('@/modules/events/CreateOrUpdateEvent.vue'),
        meta: {
            active_menu: 'events',
            middleware: 'permission',
            permissions: ['write_events'],
        },
    },
    {
        path: '/events/:id(\\d+)/edit',
        name: 'event_edit',
        component: () => import('@/modules/events/CreateOrUpdateEvent.vue'),
        meta: {
            active_menu: 'events',
            middleware: 'permission',
            permissions: ['write_events'],
        },
    },
    {
        path: '/events/:id(\\d+)',
        name: 'event_detail',
        component: () => import('@/modules/events/detail.vue'),
        meta: {
            active_menu: 'events',
            middleware: 'permission',
            permissions: ['read_events', 'write_events'],
            match_type: 'any',
        },
    },
    {
        path: '/settings',
        component: () => import('@/modules/settings/index.vue'),
        redirect: { name: 'settings_general' },
        meta: {
            active_menu: 'settings',
            middleware: 'permission',
            permissions: ['read_settings', 'write_settings'],
            match_type: 'any',
        },
        children: [
            {
                path: 'general',
                name: 'settings_general',
                component: () => import('@/modules/settings/general.vue'),
                meta: {
                    active_menu: 'settings',
                    middleware: 'permission',
                    permissions: ['read_settings', 'write_settings'],
                    match_type: 'any',
                },
            },
            {
                path: 'email-notifications',
                name: 'settings_email_notifications',
                component: () => import('@/modules/settings/email-notifications.vue'),
                meta: {
                    active_menu: 'settings',
                    middleware: 'permission',
                    permissions: ['read_settings', 'write_settings'],
                    match_type: 'any',
                },
            },
            // Team members are granted and stored by FluentEvent Pro.
            ...(window.fluentEventAdmin.has_pro ? [{
                path: 'team',
                name: 'settings_team',
                component: () => import('@/modules/settings/team.vue'),
                meta: {
                    active_menu: 'settings',
                    middleware: 'permission',
                    permissions: ['manage_all_data'],
                },
            }] : []),
        ],
    },
    {
        path: '/unauthorized',
        name: 'unauthorized',
        component: () => import('@/components/Unauthorized'),
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'notfound',
        component: () => import('@/components/NotFound'),
    }
];
