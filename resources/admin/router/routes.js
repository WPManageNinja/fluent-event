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
        component: () => import('@/modules/calendars/create.vue'),
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
        component: () => import('@/modules/events/create.vue'),
        meta: { active_menu: 'events' },
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('@/modules/settings/index.vue'),
        meta: { active_menu: 'settings' },
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
