import Rest from '@/utils/http/Rest.js';
import config from '@/bootstrap/config';

/**
 * Build a resource factory closure over the server-emitted REST endpoint map.
 *
 * The returned function takes a controller name (e.g. `'User'`) and returns a
 * Proxy that exposes each endpoint-method as a lazy callable, chainable with
 * `.withQuery()` / `.withParams()` / `.withHeaders()`. Callers never touch
 * endpoint URIs directly; name-based lookup plus template interpolation keeps
 * the REST layer DRY.
 *
 * @param {Object} endpoints  Map of `ControllerName` → endpoint spec (as
 *                             localized by the framework into
 *                             `fluentEventAdmin.endpoints`).
 * @returns {(name: string) => Proxy}
 */
const createResourceFactory = (endpoints) => {
    /**
     * Append the `Controller` suffix if the caller passed the short name.
     *
     * @param {string} name
     * @returns {string}
     */
    const normalizeControllerName = (name) => {
        return name.endsWith('Controller') ? name : `${name}Controller`;
    };

    /**
     * Look up the endpoint map entry for a given controller name. Falls back
     * to a dotted-suffix match so nested namespaces (`Foo.Bar.UserController`)
     * resolve on the bare `UserController` name.
     *
     * @param {string} name
     * @returns {Object}      Endpoint spec keyed by method name.
     * @throws {Error}        When no matching controller is registered.
     */
    const resolveTarget = (name) => {
        const normalized = normalizeControllerName(name);
        if (endpoints[normalized]) return endpoints[normalized];

        for (const key in endpoints) {
            if (key.split('.').pop() === normalized) {
                return endpoints[key];
            }
        }

        throw new Error(`Unknown resource "${name}"`);
    };

    /**
     * Attach a query object to the endpoint URI as `?k=v&...`. Uses the URL
     * constructor so callers don't need to encode values themselves.
     *
     * @param {string} uri
     * @param {Object} query
     * @returns {string}
     */
    const buildUrl = (uri, query) => {
        const url = new URL(uri, config.rest.url);

        Object.entries(query).forEach(
            ([k, v]) => url.searchParams.append(k, v)
        );

        return uri + url.search;
    };

    /**
     * Interpolate `{placeholders}` and optional `{placeholder?}` in a URI
     * template from a positional args array. Collapses duplicate slashes
     * and trims a trailing slash so the result can be concatenated safely.
     *
     * @param {string} template
     * @param {Array}  args
     * @returns {string}
     * @throws {Error}  When a required `{param}` has no corresponding arg.
     */
    const parseUri = (template, args) => {
        let index = 0;
        return template.replace(/{([^}?]*)(\?)?}/g, (_, key, optional) => {
            if (index < args.length) {
                return args[index++];
            } else if (optional === '?') {
                return '';
            } else {
                throw new Error(`Missing required parameter "${key}"`);
            }
        }).replace(/\/{2,}/g, '/').replace(/\/$/, '');
    };

    return (controllerName) => {
        const target = resolveTarget(
            controllerName[0].toUpperCase() + controllerName.slice(1)
        );

        const state = {
            query: {},
            params: {},
            headers: {},
        };

        return new Proxy(target, {
            get(_, prop, receiver) {
                if (prop === 'withQuery') {
                    return (q) => {
                        state.query = { ...state.query, ...q };
                        return receiver;
                    };
                }

                if (prop === 'withParams') {
                    return (p) => {
                        state.params = (p instanceof FormData) ? p : { ...state.params, ...p };
                        return receiver;
                    };
                }

                if (prop === 'withHeaders') {
                    return (h) => {
                        state.headers = { ...state.headers, ...h };
                        return receiver;
                    };
                }

                const endpoint = target[`_${prop}`];
                if (!endpoint) {
                    throw new Error(`Undefined method "${prop}" on resource "${controllerName}"`);
                }

                return async (...args) => {
                    const uri = buildUrl(parseUri(endpoint.uri, args), {
                        ...state.query,
                    });

                    const method = endpoint.methods[0].toLowerCase();
                    const headers = { ...state.headers };
                    const params = state.params instanceof FormData
                        ? state.params
                        : { ...state.params };

                    // Reset state after call
                    state.query = {};
                    state.params = {};
                    state.headers = {};

                    return await Rest[method](uri, params, headers);
                };
            }
        });
    };
};

export default createResourceFactory(config.endpoints);
