import { describe, it, expect, vi, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
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
    it('shows the range the server drew, which caps the span', () => {
        const $requestServer = vi.fn(({ onSuccess }) => onSuccess({
            range: { start: '2021-10-10', end: '2026-10-10' }, xAxisData: [], series: []
        }));
        const wrapper = shallowMount(GuestGrowth, {
            props: { sectionTitle: 'Guest Trends', endPoint: 'reports/guest-trends' },
            global: { mixins: [main, { methods: { $requestServer } }] }
        });

        wrapper.vm.range = ['2001-01-01', '2026-10-10'];
        wrapper.vm.fetchChartData();

        expect(wrapper.vm.range).toEqual(['2021-10-10', '2026-10-10']);
    });
});
