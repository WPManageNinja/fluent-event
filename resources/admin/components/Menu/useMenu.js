import { ref, computed } from 'vue';
import { useRoute } from 'vue-router';

/**
 * Composable that filters the router's route list into a menu-friendly
 * shape (drops hidden, dynamic, and `NotFound` routes) and exposes helpers
 * for the MenuItem template. Wrap your route array once and pull helpers
 * out of the returned object.
 *
 * @param {Array<Object>} routes
 * @returns {{
 *   slugify: (v:string)=>string,
 *   visibleRoutes: import('vue').ComputedRef<Array>,
 *   isActiveRoute: (r:Object)=>boolean,
 *   isNotRealRoute: (route:Object)=>boolean,
 *   visibleChildren: (route:Object)=>Array,
 *   hasVisibleChildren: (route:Object)=>boolean,
 * }}
 */
export function useMenu(routes) {
    const route = useRoute();

    /**
     * Keep routes that aren't the 404 fallback.
     * @param {string} name
     * @returns {boolean}
     */
    function isNotFound(name) {
        return name && name.toLowerCase() !== 'notfound';
    }

    const visibleRoutes = computed(() => {
        return Array.isArray(routes) ? routes.filter(route =>
            route.meta?.visible !== false &&
            !route.path.includes(':') &&
            isNotFound(route.name)
        ) : [];
    });

    /**
     * True when the route has at least one child that should render in the
     * menu (not hidden, not dynamic, not `NotFound`).
     * @param {Object} route
     * @returns {boolean}
     */
    function hasVisibleChildren(route) {
        return route.children?.some(child =>
            child.meta?.visible !== false &&
            child.meta?.showInMenu !== false &&
            !child.path.includes(':') &&
            isNotFound(child.name)
        );
    }

    /**
     * True for "group header" routes — they hold children but `path: '/null'`
     * marks them as non-navigable container entries.
     * @param {Object} route
     * @returns {boolean}
     */
    function isNotRealRoute(route) {
        return hasVisibleChildren(route) && route.path === '/null';
    }

    /**
     * Return the filtered list of child routes that should appear in the menu.
     * @param {Object} route
     * @returns {Array}
     */
    function visibleChildren(route) {
        return (route.children || []).filter(child =>
            child.meta?.visible !== false &&
            child.meta?.showInMenu !== false &&
            !child.path.includes(':') &&
            isNotFound(child.name)
        );
    }

    /**
     * True when `r` matches the currently-active route by name or path.
     * Drives the menu-item "highlighted" state.
     * @param {Object} r
     * @returns {boolean}
     */
    function isActiveRoute(r) {
        return route.name === r.name || route.path === r.path;
    }

    /**
     * Lowercase + hyphenate whitespace runs. Minimal slugifier used for
     * generating stable DOM ids from labels.
     * @param {string} value
     * @returns {string}
     */
    function slugify(value) {
        return value.toLowerCase().replace(/\s+/g, '-');
    }

    return {
        slugify,
        visibleRoutes,
        isActiveRoute,
        isNotRealRoute,
        visibleChildren,
        hasVisibleChildren,
    };
}
