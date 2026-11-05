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
    route: {},
};
