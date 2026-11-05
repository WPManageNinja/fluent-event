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
        meta: { active_menu: 'calendars' },
    },
    {
        path: '/calendars/new',
        name: 'calendar_create',
        component: () => import('@/modules/calendars/CreateOrUpdateCalendar.vue'),
        meta: { active_menu: 'calendars' },
    },
    {
        path: '/calendars/:id(\\d+)/edit',
        name: 'calendar_edit',
        component: () => import('@/modules/calendars/CreateOrUpdateCalendar.vue'),
        meta: { active_menu: 'calendars' },
    },
    {
        path: '/calendars/:id(\\d+)',
        name: 'calendar_detail',
        component: () => import('@/modules/calendars/detail.vue'),
        meta: { active_menu: 'calendars' },
    },
    {
        path: '/events',
        name: 'events',
        component: () => import('@/modules/events/index.vue'),
        meta: { active_menu: 'events' },
    },
    {
        path: '/events/new',
        name: 'event_create',
        component: () => import('@/modules/events/CreateOrUpdateEvent.vue'),
        meta: { active_menu: 'events' },
    },
    {
        path: '/events/:id(\\d+)/edit',
        name: 'event_edit',
        component: () => import('@/modules/events/CreateOrUpdateEvent.vue'),
        meta: { active_menu: 'events' },
    },
    {
        path: '/events/:id(\\d+)',
        name: 'event_detail',
        component: () => import('@/modules/events/detail.vue'),
        meta: { active_menu: 'events' },
    },
    {
        path: '/settings',
        component: () => import('@/modules/settings/index.vue'),
        redirect: { name: 'settings_general' },
        meta: { active_menu: 'settings' },
        children: [
            {
                path: 'general',
                name: 'settings_general',
                component: () => import('@/modules/settings/general.vue'),
                meta: { active_menu: 'settings' },
            },
            {
                path: 'email-notifications',
                name: 'settings_email_notifications',
                component: () => import('@/modules/settings/email-notifications.vue'),
                meta: { active_menu: 'settings' },
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'notfound',
        component: () => import('@/components/NotFound'),
    }
];
