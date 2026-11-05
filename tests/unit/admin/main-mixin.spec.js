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

    it('labels every entry mode', () => {
        expect(component().$entryTypeLabel('rsvp')).toBe('RSVP');
        expect(component().$entryTypeLabel('open')).toBe('Open');
        expect(component().$entryTypeLabel('none')).toBe('None');
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
