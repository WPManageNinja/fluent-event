// import Rest from '@/utils/Http/Rest';
// import Cookie from '@/utils/Cookie';
// import Storage from '@/utils/Storage';

/**
 * Resolve the current authenticated user from the app's global properties.
 * The framework seeds `appVars.me` during bootstrap from
 * `fluentEventAdmin.me`, so middlewares can read it synchronously
 * without a round-trip.
 *
 * @param  {import('vue').App} app
 * @returns {Object}
 */
export const getCurrentUser = (app) => {
    return app.config.globalProperties.appVars.me;
}

export default {
    global: {
        before: {},
        after: {},
    },
    route: {
        permission: async (to, from, app) => {
            const user = getCurrentUser(app);
            const required = to.meta?.permissions || [];

            if (!required.length) return true;

            const granted = user.permissions || [];
            const matches = to.meta?.match_type === 'any'
                ? required.some(key => granted.includes(key))
                : required.every(key => granted.includes(key))

            const allowed = user.is_admin
                || granted.includes('manage_all_data')
                || matches;

            if (!allowed) return { path: '/unauthorized' };
            return true;
        },
    },
};
