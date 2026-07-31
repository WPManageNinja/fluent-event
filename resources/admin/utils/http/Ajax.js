/**
 * Send a request to WordPress's `admin-ajax.php` endpoint, applying the
 * plugin's `hook_prefix` to the action name and attaching the current
 * nonce automatically. Supports both plain objects and `FormData` payloads
 * across GET/POST/PUT/PATCH/DELETE.
 *
 * Prefer the REST helper (`utils/http/Rest.js`) for new code — this exists
 * for parity with legacy `wp_ajax_*` handlers.
 *
 * @param  {('GET'|'POST'|'PUT'|'PATCH'|'DELETE')} method
 * @param  {string}        action   Short action name; prefixed server-side.
 * @param  {Object|FormData} [data={}]
 * @param  {Object}        [headers={}]
 * @returns {Promise<*>}   Parsed JSON or raw text, depending on response
 *                         content-type. Rejects the parsed body on non-2xx.
 */
const ajaxRequest = function (method, action, data = {}, headers = {}) {
    const config = FLUENT_EVENT_APP.vue.config.globalProperties.appVars;
    
    const url = window.ajaxurl || '/wp-admin/admin-ajax.php';

    if (data instanceof FormData) {
        data.append('action', `${config.hook_prefix}${action}`);
        data.append('_wpnonce', config.nonce);
    } else if (typeof data === 'object' && data !== null) {
        data.action = `${config.hook_prefix}${action}`;
        data._wpnonce = config.nonce;
    } else {
        throw new Error('data must be either a plain object or FormData');
    }

    let fetchUrl = url;
    let fetchOptions = { method, headers: { ...headers } };

    if (method.toUpperCase() === 'GET') {
        // For GET, serialize object to query string
        if (data instanceof FormData) {
            // Convert FormData to URLSearchParams for GET
            const params = new URLSearchParams();
            for (const [key, value] of data.entries()) {
                params.append(key, value);
            }
            fetchUrl += '?' + params.toString();
        } else {
            fetchUrl += '?' + new URLSearchParams(data).toString();
        }
    } else {
        // For POST/PUT/PATCH/DELETE
        if (data instanceof FormData) {
            // Send FormData directly (fetch sets Content-Type automatically)
            fetchOptions.body = data;
        } else {
            // Send plain object as URL-encoded form data
            fetchOptions.body = new URLSearchParams(data).toString();
            fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
        }
    }

    return fetch(fetchUrl, fetchOptions)
        .then(async (response) => {
            let parsed;
            const contentType = response.headers.get('content-type');

            if (contentType && contentType.includes('application/json')) {
                parsed = await response.json();
            } else {
                parsed = await response.text();
            }

            if (!response.ok) {
                return Promise.reject(parsed);
            }

            return parsed;
        })
        .catch((error) => Promise.reject(error));
};

export default {
    get(action, data = {}, headers = {}) {
        return ajaxRequest('GET', action, data, headers);
    },
    post(action, data = {}, headers = {}) {
        return ajaxRequest('POST', action, data, headers);
    },
    delete(action, data = {}, headers = {}) {
        return ajaxRequest('DELETE', action, data, headers);
    },
    put(action, data = {}, headers = {}) {
        return ajaxRequest('PUT', action, data, headers);
    },
    patch(action, data = {}, headers = {}) {
        return ajaxRequest('PATCH', action, data, headers);
    }
};
