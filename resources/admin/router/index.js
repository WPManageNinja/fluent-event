import { createRouter, createWebHashHistory } from 'vue-router';
import routes from '@/router/routes';
import {
    runRouteMiddleware,
    runBeforeMiddleware,
    runAfterMiddleware,
} from './middlewareHandler';

/**
 * Execute global and route middleware before and after each route.
 * @param  * @param {Object} app - The main vue app instance.
 * @param  {object} router - The vue router
 * @return {object} The vue router
 */
function attachMiddleware(app, router) {
    router.beforeEach(async (to, from) => {
        const globalResult = await runBeforeMiddleware(to, from, app);

        if (globalResult !== true) return globalResult;

        const routeResult = await runRouteMiddleware(to, from, app);

        if (routeResult !== true) return routeResult;

        return true;
    });

    router.afterEach(async (to, from) => {
        await runAfterMiddleware(to, from, app);
    });

    return router;
};

/**
 * Create the admin Vue Router instance from the static route table.
 * @param {Object} app - The main vue app instance.
 * @returns {Router} Vue Router instance
 */
export default function createAdminRouter(app) {
    const router = createRouter({
        strict: true,
        history: createWebHashHistory(),
        routes,
    });

    return attachMiddleware(app, router);
}
