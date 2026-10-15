import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

const appVars = () => ({ rest: { namespace: 'fluent-event', version: 'v2', nonce: 'rest-nonce' } });

// wp.apiFetch with its middleware chain, so requests run through what Rest.js registers.
const installWp = (respond) => {
    const middlewares = [];
    const apiFetch = vi.fn((options) => {
        const run = (index, opts) => (index < middlewares.length
            ? middlewares[index](opts, next => run(index + 1, next))
            : respond(opts));
        return run(0, options);
    });
    apiFetch.use = vi.fn(middleware => middlewares.push(middleware));

    window.wp = {
        apiFetch,
        url: {
            addQueryArgs: (url, args) => {
                const [path, query = ''] = url.split('?');
                const params = new URLSearchParams(query);
                Object.entries(args).forEach(([key, value]) => params.set(key, value));
                return `${path}?${params.toString()}`;
            }
        }
    };

    return { apiFetch, middlewares };
};

const json = (body, init = {}) => new Response(JSON.stringify(body), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
    ...init
});

let Rest;
let ResponseProxyItr;

beforeEach(async () => {
    // Rest.js keeps the app instance and its middleware queue in module scope.
    vi.resetModules();
    Rest = (await import('@/utils/http/Rest')).default;
    ResponseProxyItr = (await import('@/utils/http/ResponseProxyItr')).default;
});

afterEach(() => {
    delete window.wp;
    vi.useRealTimers();
});

describe('Rest', () => {
    it('refuses to send before the app instance is set', async () => {
        installWp(() => json({}));

        await expect(Rest.get('events')).rejects.toThrow('Rest instance not set');
    });

    it('builds the namespaced path with the nonce and a cache-busting timestamp', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-15T00:00:00Z'));
        const { apiFetch } = installWp(() => json({}));
        Rest.setInstance({ config: { globalProperties: { appVars: appVars() } } });

        await Rest.get('/events', { page: 2, search: 'meet up' });

        const options = apiFetch.mock.calls[0][0];
        expect(options.path).toBe(`fluent-event/v2/events?page=2&search=meet+up&query_timestamp=${Date.now()}`);
        expect(options.headers['X-WP-Nonce']).toBe('rest-nonce');
        expect(options.body).toBeUndefined();
        expect(options.parse).toBe(false);
    });

    it('sends a JSON body for writes', async () => {
        const { apiFetch } = installWp(() => json({}));
        Rest.setInstance({ config: { globalProperties: { appVars: appVars() } } });

        await Rest.patch('events/12', { title: 'Renamed' });

        const options = apiFetch.mock.calls[0][0];
        expect(options.method).toBe('PATCH');
        expect(options.body).toBe('{"title":"Renamed"}');
        expect(options.headers['Content-Type']).toBe('application/json');
    });

    it('sends FormData untouched so the browser sets the multipart boundary', async () => {
        const { apiFetch } = installWp(() => json({}));
        Rest.setInstance({ config: { globalProperties: { appVars: appVars() } } });
        const form = new FormData();
        form.append('file', 'cover');

        await Rest.post('media', form);

        const options = apiFetch.mock.calls[0][0];
        expect(options.body).toBe(form);
        expect(options.headers['Content-Type']).toBeUndefined();
    });

    it('reads the nonce on every call so a heartbeat renewal takes effect', async () => {
        const { apiFetch } = installWp(() => json({}));
        const vars = appVars();
        Rest.setInstance({ config: { globalProperties: { appVars: vars } } });

        await Rest.get('events');
        vars.rest.nonce = 'renewed-nonce';
        await Rest.get('events');

        expect(apiFetch.mock.calls[1][0].headers['X-WP-Nonce']).toBe('renewed-nonce');
    });

    it('wraps a response so its JSON reads like plain properties', async () => {
        installWp(() => json({ event: { id: 12 }, message: 'Saved' }));
        Rest.setInstance({ config: { globalProperties: { appVars: appVars() } } });

        const response = await Rest.get('events/12');

        expect(response).toBeInstanceOf(ResponseProxyItr);
        expect(response.event).toEqual({ id: 12 });
        expect(response.message).toBe('Saved');
        expect(response.status).toBe(200);
    });

    it('reads numeric headers back as numbers', async () => {
        installWp(() => json([], { headers: { 'X-WP-Total': '42', 'X-Ratio': '0.5', 'X-Label': 'abc' } }));
        Rest.setInstance({ config: { globalProperties: { appVars: appVars() } } });

        const response = await Rest.get('events');

        expect(response.header('X-WP-Total')).toBe(42);
        expect(response.header('X-Ratio')).toBe(0.5);
        expect(response.header('X-Label')).toBe('abc');
        expect(response.header('X-Missing')).toBeNull();
    });

    it('rejects with the wrapped error response', async () => {
        installWp(() => Promise.reject(json({ title: { required: 'Title is required' } }, { status: 422 })));
        Rest.setInstance({ config: { globalProperties: { appVars: appVars() } } });

        const error = await Rest.post('events', {}).catch(e => e);

        expect(error).toBeInstanceOf(ResponseProxyItr);
        expect(error.status).toBe(422);
        expect(error.errors).toEqual({ title: { required: 'Title is required' } });
    });

    it('registers its response middleware once', async () => {
        const { apiFetch } = installWp(() => json({}));
        Rest.setInstance({ config: { globalProperties: { appVars: appVars() } } });

        await Rest.get('events');
        await Rest.get('calendars');

        expect(apiFetch.use).toHaveBeenCalledTimes(1);
    });

    it("leaves other plugins' apiFetch calls alone", async () => {
        const raw = json({ id: 1 });
        const { apiFetch } = installWp(() => raw);
        Rest.setInstance({ config: { globalProperties: { appVars: appVars() } } });
        await Rest.get('events');

        // A core block editor request through the same apiFetch, without our flag.
        const result = await apiFetch({ path: 'wp/v2/posts/1' });

        expect(result).toBe(raw);
    });

    it('runs middleware added through use() on our requests only', async () => {
        const { apiFetch } = installWp(() => json({}));
        const seen = vi.fn((options, next) => next(options));
        Rest.use(seen);
        Rest.setInstance({ config: { globalProperties: { appVars: appVars() } } });

        await Rest.get('events');
        await apiFetch({ path: 'wp/v2/posts/1' });

        expect(seen).toHaveBeenCalledTimes(1);
    });

    it('marks an aborted request so the error handler can tell it apart', () => {
        Rest.setInstance({ config: { globalProperties: { appVars: appVars(), $t: (text) => `[${text}]` } } });

        expect(() => Rest.abort()).toThrow('[Request aborted.]');
        try {
            Rest.abort('Stopped');
        } catch (error) {
            expect(error.isAborted).toBe(true);
            expect(error.message).toBe('Stopped');
        }
    });
});

describe('ResponseProxyItr', () => {
    const wrap = (responseJSON, extra = {}) => new ResponseProxyItr({ status: 200, statusText: 'OK', responseJSON, ...extra });

    it('lets HTTP fields shadow a JSON key of the same name', () => {
        // A payload with a top-level `status` would read as the HTTP code; nest such fields.
        expect(wrap({ status: 'public' }).status).toBe(200);
    });

    it('exposes the whole payload as errors when there is no errors key', () => {
        expect(wrap({ title: { required: 'Required' } }).errors).toEqual({ title: { required: 'Required' } });
    });

    it('spreads, iterates and lists keys over the JSON payload', () => {
        const response = wrap({ a: 1, b: 2 });

        expect(Object.keys(response)).toEqual(['a', 'b']);
        expect({ ...response }).toEqual({ a: 1, b: 2 });
        expect([...response]).toEqual([1, 2]);
        expect(response.all()).toEqual({ a: 1, b: 2 });
    });
});
