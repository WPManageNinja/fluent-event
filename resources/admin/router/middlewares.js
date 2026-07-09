// import Rest from '@/utils/Http/Rest';
// import Cookie from '@/utils/Cookie';
// import Storage from '@/utils/Storage';

/**
 * Resolve the current authenticated user from the app's global properties.
 * The framework seeds `appVars.me` during bootstrap from
 * `fluentFrameworkAdmin.me`, so middlewares can read it synchronously
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
        before: {
            log(to, from, app) {
                console.log('Global Before Middleware...', to, from);
                return true;
            },
        },
        after: {
            log(to, from, app) {
                console.log('Global After Middleware...', to, from);
                return true
            },
        },
    },
    route: {
        admin: async (to, from, app) => {
            const user = getCurrentUser(app);

            if (!user.is_admin) {
                return { path: '/unauthorized' };
            }

            return true;
        },
    },
};
