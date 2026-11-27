import { describe, it, expect, vi, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import dayjs from 'dayjs';
import main from '@/mixins/main';
import GuestGrowth from '@/modules/dashboard/parts/GuestGrowth.vue';

vi.mock('@/utils/http/Rest.js', () => ({
    default: { get: vi.fn(() => new Promise(() => {})), post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() }
}));

const picker = (format) => {
    window.fluentEventAdmin.date_format = format;

    return shallowMount(GuestGrowth, {
        props: { sectionTitle: 'Guest Trends', endPoint: '' },
        global: { mixins: [main, { methods: { $requestServer: vi.fn() } }] }
    }).find('.fe-dashboard__range');
};

// The picker is narrow; a long site date format ("28th September, 2026") gets the wide one so both dates fit.
describe('GuestGrowth date range width', () => {
    const original = window.fluentEventAdmin.date_format;

    afterEach(() => {
        window.fluentEventAdmin.date_format = original;
    });

    it('widens the picker for a long date format only', () => {
        expect(picker('Do MMMM, YYYY').classes()).toContain('fe-dashboard__range--wide');
        expect(picker('MMMM D, YYYY').classes()).toContain('fe-dashboard__range--wide');
        expect(picker('MMM D, YYYY').classes()).not.toContain('fe-dashboard__range--wide');
        expect(picker('YYYY-MM-DD').classes()).not.toContain('fe-dashboard__range--wide');
    });
});

describe('GuestGrowth range', () => {
    it('offers only the last five years, up to today', () => {
        const vm = shallowMount(GuestGrowth, {
            props: { sectionTitle: 'Guest Trends', endPoint: '' },
            global: { mixins: [main, { methods: { $requestServer: vi.fn() } }] }
        }).vm;
        const day = (offset, unit) => dayjs().add(offset, unit).toDate();

        expect(vm.outsideReportWindow(day(0, 'day'))).toBe(false);
        expect(vm.outsideReportWindow(day(-5, 'year'))).toBe(false);
        expect(vm.outsideReportWindow(day(1, 'day'))).toBe(true);
        expect(vm.outsideReportWindow(dayjs().subtract(5, 'year').subtract(1, 'day').toDate())).toBe(true);
    });

    it('counts today on the site clock, where a site ahead of the browser is already tomorrow', () => {
        const original = window.fluentEventAdmin.timezone;
        vi.useFakeTimers({ now: new Date('2026-11-27T12:00:00Z'), toFake: ['Date'] });
        window.fluentEventAdmin.timezone = 'Pacific/Kiritimati';

        try {
            const vm = shallowMount(GuestGrowth, {
                props: { sectionTitle: 'Guest Trends', endPoint: '' },
                global: { mixins: [main, { methods: { $requestServer: vi.fn() } }] }
            }).vm;

            expect(vm.range).toEqual(['2026-11-22', '2026-11-28']);
            expect(vm.outsideReportWindow(new Date(2026, 10, 28))).toBe(false);
            expect(vm.outsideReportWindow(new Date(2026, 10, 29))).toBe(true);
        } finally {
            vi.useRealTimers();
            window.fluentEventAdmin.timezone = original;
        }
    });
});
