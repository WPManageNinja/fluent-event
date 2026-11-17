import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import StartDisplayPreview from '../../../resources/admin/components/StartDisplayPreview.vue';

afterEach(() => {
    vi.useRealTimers();
});

describe('StartDisplayPreview', () => {
    // The event forms' help shows the countdown ticking, from a sample start 2 days 04:58:50 away.
    it('ticks the countdown each second, and stops when the help goes away', async () => {
        vi.useFakeTimers();
        const wrapper = mount(StartDisplayPreview, { props: { value: 'countdown' }, global: { mocks: { $t: (text) => text } } });
        const values = () => wrapper.findAll('.fe-start-preview__value').map((value) => value.text());

        expect(values()).toEqual(['02', '04', '58', '50']);

        vi.advanceTimersByTime(1000);
        await wrapper.vm.$nextTick();
        expect(values()).toEqual(['02', '04', '58', '49']);

        // The test DOM keeps a timer of its own, so count the one the countdown clears.
        const timers = vi.getTimerCount();
        wrapper.unmount();
        expect(vi.getTimerCount()).toBe(timers - 1);
    });
});
