import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ElNotification } from 'element-plus';
import Rest from '@/utils/http/Rest.js';
import ResponseProxyItr from '@/utils/http/ResponseProxyItr';
import main from '@/mixins/main';
import { translate } from '../../setup';

vi.mock('element-plus', () => ({
    ElNotification: vi.fn(),
    ElMessageBox: { alert: vi.fn(), confirm: vi.fn() }
}));

vi.mock('@/utils/http/Rest.js', () => ({
    default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() }
}));

// A component instance as the mixin sees it: its own state plus every mixin method bound to it.
const component = (state = {}) => {
    const vm = { $t: translate, ...state };
    Object.entries(main.methods).forEach(([name, fn]) => {
        vm[name] = fn.bind(vm);
    });
    return vm;
};

const deferred = () => {
    let resolve, reject;
    const promise = new Promise((res, rej) => {
        resolve = res;
        reject = rej;
    });
    return { promise, resolve, reject };
};

const flush = () => new Promise(resolve => setTimeout(resolve));

const lastNotification = () => ElNotification.mock.calls.at(-1)[0];

beforeEach(() => {
    vi.clearAllMocks();
});

describe('text helpers', () => {
    const vm = component();

    it('flattens a nested validation payload into one message per line', () => {
        expect(vm.$convertToText({ title: { required: 'Title is required' }, end_at: { after: 'End is too early' } }))
            .toBe('Title is required<br />End is too early');
    });

    it('joins with a plain separator for notifications that render text', () => {
        expect(vm.$convertToText(['One', ['Two', 'Three']], ' ')).toBe('One Two Three');
    });

    it('returns an empty string for nothing', () => {
        expect(vm.$convertToText(null)).toBe('');
        expect(vm.$convertToText(undefined)).toBe('');
    });

    it('title-cases snake, kebab and camel case', () => {
        expect(vm.$titleCase('user_profile')).toBe('User Profile');
        expect(vm.$titleCase('fluent-event')).toBe('Fluent Event');
        expect(vm.$titleCase('emailNotifications')).toBe('Email Notifications');
    });

    it('slugifies accents, punctuation and runs of dashes', () => {
        expect(vm.$slugify('Café Meetup 2026!')).toBe('cafe-meetup-2026');
        expect(vm.$slugify('  --Hello   World--  ')).toBe('hello-world');
    });
});

describe('status and entry type labels', () => {
    it('maps event statuses to tag types and falls back to info', () => {
        const vm = component();

        expect(vm.$eventStatusType('public')).toBe('success');
        expect(vm.$eventStatusType('unlisted')).toBe('warning');
        expect(vm.$eventStatusType('private')).toBe('primary');
        expect(vm.$eventStatusType('draft')).toBe('info');
        expect(vm.$eventStatusType('archived')).toBe('info');
    });

    it('translates a status label', () => {
        window.fluentEventAdmin.i18n = { Private: 'Privat' };

        expect(component().$eventStatusLabel('private')).toBe('Privat');
        expect(component().$calendarStatusLabel('private')).toBe('Privat');
    });

    it('shows an unknown status or entry type as stored', () => {
        // `ticketed` is a documented future entry mode; it must render rather than vanish.
        expect(component().$eventStatusLabel('archived')).toBe('archived');
        expect(component().$entryTypeLabel('ticketed')).toBe('ticketed');
    });

    it('labels both entry modes', () => {
        expect(component().$entryTypeLabel('rsvp')).toBe('RSVP');
        expect(component().$entryTypeLabel('open')).toBe('Open');
    });
});

describe('url and colour guards', () => {
    const vm = component();

    it('keeps an http or https URL', () => {
        expect(vm.$httpUrl('https://meet.example.test/abc')).toBe('https://meet.example.test/abc');
        expect(vm.$httpUrl('HTTP://example.test')).toBe('HTTP://example.test');
    });

    it('drops any other scheme so it cannot be bound to an href', () => {
        expect(vm.$httpUrl('javascript:alert(1)')).toBe('');
        expect(vm.$httpUrl('data:text/html,hi')).toBe('');
        expect(vm.$httpUrl(null)).toBe('');
    });

    it("uses a calendar's theme colour, or a neutral token without one", () => {
        expect(vm.$calendarColor({ meta: { theme_color: '#ff6600' } })).toBe('#ff6600');
        expect(vm.$calendarColor({ meta: {} })).toBe('var(--fe-secondary-border, #9ca3af)');
        expect(vm.$calendarColor(null)).toBe('var(--fe-secondary-border, #9ca3af)');
    });

    it('joins a route onto the REST base without doubling slashes', () => {
        window.fluentEventAdmin.rest.url = 'https://example.test/wp-json/fluent-event/v2/';

        expect(vm.$url('/events/12')).toBe('https://example.test/wp-json/fluent-event/v2/events/12');
    });
});

describe('number and date formatting', () => {
    const vm = component();

    it('groups thousands and shows zero for an empty amount', () => {
        expect(vm.$formatNumber(1234567)).toBe('1,234,567');
        expect(vm.$formatNumber(null)).toBe('0');
    });

    it('hides an empty amount when asked', () => {
        expect(vm.$formatNumber(0, true)).toBe('');
    });

    it('formats currency but renders nothing for a missing value', () => {
        expect(vm.$formatCurrency(12.5)).toBe('$12.50');
        expect(vm.$formatCurrency(0)).toBe('$0.00');
        expect(vm.$formatCurrency(null)).toBe('');
    });

    it("formats dates and times with the site's WordPress formats", () => {
        window.fluentEventAdmin.date_format = 'D MMMM YYYY';
        window.fluentEventAdmin.time_format = 'HH:mm';

        expect(vm.$formatDate('2026-10-13 18:30:00')).toBe('13 October 2026');
        expect(vm.$formatTime('2026-10-13 18:30:00')).toBe('18:30');
        expect(vm.$formatDateTime('2026-10-13 18:30:00')).toBe('13 October 2026 18:30');
    });

    it('supports an ordinal day from a WordPress "jS" date format', () => {
        window.fluentEventAdmin.date_format = 'MMMM Do, YYYY';

        expect(vm.$formatDate('2026-10-03')).toBe('October 3rd, 2026');
    });

    it('supports ISO week tokens from a WordPress "W" or "o" date format', () => {
        window.fluentEventAdmin.date_format = 'GGGG-[W]W';

        expect(vm.$formatDate('2026-10-15')).toBe('2026-W42');
    });

    it("formats an event's range on its own clock or the site's, dating only what falls on another day", () => {
        window.fluentEventAdmin.date_format = 'MMM D';
        window.fluentEventAdmin.time_format = 'h:mm A';
        window.fluentEventAdmin.timezone = 'Asia/Dhaka';
        const london = {
            timezone: 'Europe/London',
            start_at_local: '2026-10-19 22:00:00',
            end_at_local: '2026-10-19 23:00:00',
            start_at_site: '2026-10-20 03:00:00',
            end_at_site: '2026-10-20 04:00:00',
        };

        expect(vm.$formatTimeRange(london)).toBe('10:00 PM – 11:00 PM (Europe/London)');
        expect(vm.$formatTimeRange(london, 'site')).toBe('Oct 20 3:00 AM – 4:00 AM (Asia/Dhaka)');
        expect(vm.$formatTimeRange({ ...london, end_at_site: '2026-10-21 01:00:00' }, 'site'))
            .toBe('Oct 20 3:00 AM – Oct 21 1:00 AM (Asia/Dhaka)');
        expect(vm.$formatTimeRange({ ...london, end_at_local: '2026-10-21 09:00:00' }))
            .toBe('10:00 PM – Oct 21 9:00 AM (Europe/London)');
    });

    it('lets an explicit format win over the site format', () => {
        expect(vm.$formatDate('2026-10-13', 'YYYY/MM/DD')).toBe('2026/10/13');
    });

    it('falls back to a readable format when the site has none', () => {
        window.fluentEventAdmin.date_format = '';
        window.fluentEventAdmin.time_format = '';

        expect(vm.$formatDate('2026-10-13 18:30:00')).toBe('October 13, 2026');
        expect(vm.$formatTime('2026-10-13 18:30:00')).toBe('6:30 pm');
    });

    it('titles the browser tab after the page and the plugin', () => {
        if (!document.querySelector('head title')) {
            document.head.appendChild(document.createElement('title'));
        }

        vm.$changeTitle('Events');

        expect(document.title).toBe('Events - Fluent Event');
    });
});

describe('$userCan', () => {
    const as = (me) => {
        window.fluentEventAdmin.me = me;
        return component();
    };

    it('allows everything for a WordPress administrator', () => {
        expect(as({ is_admin: true, permissions: [] }).$userCan('write_settings')).toBe(true);
    });

    it('allows everything for a manage_all_data holder', () => {
        expect(as({ is_admin: false, permissions: ['manage_all_data'] }).$userCan(['write_events', 'write_settings'])).toBe(true);
    });

    it('requires every key by default', () => {
        const vm = as({ is_admin: false, permissions: ['read_events'] });

        expect(vm.$userCan('read_events')).toBe(true);
        expect(vm.$userCan(['read_events', 'write_events'])).toBe(false);
    });

    it('accepts any one key when asked to match any', () => {
        const vm = as({ is_admin: false, permissions: ['read_calendars'] });

        expect(vm.$userCan(['read_calendars', 'write_calendars'], { match_type: 'any' })).toBe(true);
    });

    it('denies when the current user is unknown', () => {
        delete window.fluentEventAdmin.me;

        expect(component().$userCan('read_events')).toBe(false);
    });
});

describe('notifications', () => {
    it('shows a plain string as the error message', () => {
        component().$handleError('Calendar not found');

        expect(lastNotification()).toMatchObject({ type: 'error', title: 'Error', message: 'Calendar not found' });
    });

    it('warns rather than errors for an aborted request', () => {
        component().$handleError({ isAborted: true, message: 'Request aborted.' });

        expect(lastNotification()).toMatchObject({ type: 'warning', message: 'Request aborted.' });
    });

    it("prefers the server's message on a REST error", () => {
        const error = new ResponseProxyItr({ status: 403, responseJSON: { code: 'forbidden', message: 'Sorry, not allowed.' } });

        component().$handleError(error);

        expect(lastNotification().message).toBe('Sorry, not allowed.');
    });

    it('joins validation messages into one line when there is no message', () => {
        const error = new ResponseProxyItr({
            status: 422,
            responseJSON: { title: { required: 'Title is required' }, end_at: { after: 'End is too early' } }
        });

        component().$handleError(error);

        expect(lastNotification().message).toBe('Title is required End is too early');
    });

    it('falls back to a generic or caller-supplied message', () => {
        component().$handleError({});
        expect(lastNotification().message).toBe('Something went wrong');

        component().$handleError({}, 'Could not save the event');
        expect(lastNotification().message).toBe('Could not save the event');
    });

    it('titles a notification by its type', () => {
        window.fluentEventAdmin.i18n = { Success: 'Erfolg' };

        component().$notify('Saved', 'success');
        expect(lastNotification()).toMatchObject({ type: 'success', title: 'Erfolg', message: 'Saved' });

        component().$notify('Heads up', 'custom');
        expect(lastNotification().title).toBe('Custom');
    });
});

describe('$requestServer', () => {
    it('refuses a state the component never declared', () => {
        const vm = component({ error: '' });

        expect(() => vm.$requestServer({ path: 'events' })).toThrow(/unknown state/);
    });

    it('marks loading for the life of the request and hands over the response', async () => {
        const request = deferred();
        Rest.get.mockReturnValue(request.promise);
        const onSuccess = vi.fn();
        const vm = component({ loading: false, error: 'old error' });

        vm.$requestServer({ path: 'events', payload: { page: 2 }, onSuccess });

        expect(Rest.get).toHaveBeenCalledWith('events', { page: 2 });
        expect(vm.loading).toBe(true);

        request.resolve({ events: [] });
        await flush();

        expect(vm.loading).toBe(false);
        expect(vm.error).toBe('');
        expect(onSuccess).toHaveBeenCalledWith({ events: [] });
    });

    it("announces success with the server's message when asked", async () => {
        Rest.post.mockResolvedValue({ message: 'Event has been created' });
        const vm = component({ loading: false, error: '' });

        vm.$requestServer({ method: 'post', path: 'events', successNotify: true });
        await flush();

        expect(lastNotification()).toMatchObject({ type: 'success', message: 'Event has been created' });
    });

    it('records a failure in the error state and only notifies when asked', async () => {
        Rest.get.mockRejectedValue({ message: 'Server unavailable' });
        const onError = vi.fn();
        const vm = component({ loading: false, error: '' });

        vm.$requestServer({ path: 'events', onError });
        await flush();

        expect(vm.error).toBe('Server unavailable');
        expect(vm.loading).toBe(false);
        expect(onError).toHaveBeenCalledWith({ message: 'Server unavailable' });
        expect(ElNotification).not.toHaveBeenCalled();

        vm.$requestServer({ path: 'events', errorNotify: true });
        await flush();

        expect(lastNotification()).toMatchObject({ type: 'error', message: 'Server unavailable' });
    });

    it('ignores a second submit while the first mutation is in flight', () => {
        Rest.post.mockReturnValue(deferred().promise);
        const vm = component({ saving: false });

        vm.$requestServer({ method: 'post', path: 'events', loadingState: 'saving', errorState: null });
        vm.$requestServer({ method: 'post', path: 'events', loadingState: 'saving', errorState: null });

        expect(Rest.post).toHaveBeenCalledTimes(1);
    });

    it('lets a new read through while an earlier one is in flight', () => {
        Rest.get.mockReturnValue(deferred().promise);
        const vm = component({ loading: false, error: '' });

        vm.$requestServer({ path: 'events', payload: { page: 1 } });
        vm.$requestServer({ path: 'events', payload: { page: 2 } });

        expect(Rest.get).toHaveBeenCalledTimes(2);
    });

    it('keeps the newest read when an older one answers last', async () => {
        const older = deferred();
        const newer = deferred();
        Rest.get.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);
        const shown = [];
        const vm = component({ loading: false, error: '' });

        vm.$requestServer({ path: 'events', payload: { search: 'a' }, onSuccess: r => shown.push(r.search) });
        vm.$requestServer({ path: 'events', payload: { search: 'ab' }, onSuccess: r => shown.push(r.search) });

        newer.resolve({ search: 'ab' });
        await flush();
        older.resolve({ search: 'a' });
        await flush();

        expect(shown).toEqual(['ab']);
        expect(vm.loading).toBe(false);
    });

    it('keeps loading on until the newest read settles', async () => {
        const older = deferred();
        const newer = deferred();
        Rest.get.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);
        const vm = component({ loading: false, error: '' });

        vm.$requestServer({ path: 'events' });
        vm.$requestServer({ path: 'events' });
        older.reject({ message: 'stale failure' });
        await flush();

        expect(vm.loading).toBe(true);
        expect(vm.error).toBe('');
    });

    it('tracks requests without a loading state by their path', async () => {
        const older = deferred();
        const newer = deferred();
        Rest.get.mockReturnValueOnce(older.promise).mockReturnValueOnce(newer.promise);
        const onSuccess = vi.fn();
        const vm = component();

        vm.$requestServer({ path: 'calendars', loadingState: null, errorState: null, onSuccess });
        vm.$requestServer({ path: 'calendars', loadingState: null, errorState: null, onSuccess });
        older.resolve('stale');
        newer.resolve('fresh');
        await flush();

        expect(onSuccess.mock.calls).toEqual([['fresh']]);
    });
});
