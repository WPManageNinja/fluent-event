import { describe, it, expect } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import RegistrationLimitField from '@/components/RegistrationLimitField.vue';

const field = (props = {}) => shallowMount(RegistrationLimitField, {
    props: { kind: 'closes', start: '2026-11-10 10:00', id: 'fe-test-limit', modelValue: null, ...props },
    global: { mixins: [main] }
});

describe('RegistrationLimitField', () => {
    it('previews the moment a set limit works out to, and nothing while empty', () => {
        expect(field().vm.hint).toBe('');

        expect(field({ modelValue: { value: 2, unit: 'hours' } }).vm.hint).toBe('Tue, Nov 10, 2026 · 8:00 AM');
        expect(field({ kind: 'opens', modelValue: { value: 7, unit: 'days' } }).vm.hint).toBe('Tue, Nov 3, 2026 · 10:00 AM');
    });

    it.each([
        ['zero is the start itself', { value: 0, unit: 'hours' }, {}, 'Tue, Nov 10, 2026 · 10:00 AM'],
        ['minutes', { value: 45, unit: 'minutes' }, {}, 'Tue, Nov 10, 2026 · 9:15 AM'],
        ['days across a month and a year', { value: 10, unit: 'days' }, { start: '2027-01-02 09:00' }, 'Wed, Dec 23, 2026 · 9:00 AM'],
        ['a 24-hour clock', { value: 3, unit: 'hours' }, { timeFormat: 'HH:mm' }, 'Tue, Nov 10, 2026 · 07:00'],
        // Same wall-clock time a week earlier, as the server counts it, though New York's clocks go back in between.
        ['days across a DST change', { value: 7, unit: 'days' }, { start: '2026-11-03 10:00' }, 'Tue, Oct 27, 2026 · 10:00 AM']
    ])('previews %s', (label, modelValue, props, expected) => {
        expect(field({ modelValue, ...props }).vm.hint).toBe(expected);
    });

    // 3:30 AM less two hours on the event's clock is 1:30 AM; a New York browser, whose clocks skip 2 AM that
    // night, would count real hours and preview 12:30 AM.
    it("previews hours on the event's clock, whatever the browser's own DST does", () => {
        const tz = process.env.TZ;
        process.env.TZ = 'America/New_York';

        try {
            const hint = field({ start: '2027-03-14 03:30', modelValue: { value: 2, unit: 'hours' } }).vm.hint;
            expect(hint).toBe('Sun, Mar 14, 2027 · 1:30 AM');
        } finally {
            process.env.TZ = tz;
        }
    });

    it('keeps the number when the unit changes, and remembers a unit chosen before any number', () => {
        const set = field({ modelValue: { value: 5, unit: 'hours' } });
        set.vm.setUnit('minutes');
        expect(set.emitted('update:modelValue')).toEqual([[{ value: 5, unit: 'minutes' }]]);

        const empty = field({ kind: 'opens' });
        empty.vm.setUnit('hours');
        empty.vm.setValue(4);
        expect(empty.emitted('update:modelValue')).toEqual([[null], [{ value: 4, unit: 'hours' }]]);
    });

    it('takes the saved unit once the edit form loads the event', async () => {
        const wrapper = field({ kind: 'opens' });

        await wrapper.setProps({ modelValue: { value: 30, unit: 'minutes' } });

        expect(wrapper.vm.unit).toBe('minutes');
    });

    it('emits the offset with its unit, and null once the number is cleared', () => {
        const wrapper = field({ modelValue: { value: 2, unit: 'hours' } });

        wrapper.vm.setUnit('days');
        wrapper.vm.setValue(null);

        expect(wrapper.emitted('update:modelValue')).toEqual([[{ value: 2, unit: 'days' }], [null]]);
    });

    it('starts opens in days and closes in hours', () => {
        expect(field({ kind: 'opens' }).vm.unit).toBe('days');
        expect(field().vm.unit).toBe('hours');
    });
});
