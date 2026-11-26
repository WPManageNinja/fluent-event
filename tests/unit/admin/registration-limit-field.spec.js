import { describe, it, expect } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import RegistrationLimitField from '@/components/RegistrationLimitField.vue';

const field = (props = {}) => shallowMount(RegistrationLimitField, {
    props: { kind: 'closes', start: '2026-11-10 10:00', end: '2026-11-10 12:00', id: 'fe-test-limit', modelValue: null, ...props },
    global: { mixins: [main] }
});

const rel = (type, value, unit) => ({ type, value, unit, at: null });

describe('RegistrationLimitField', () => {
    it('starts on opening immediately and closing when the event ends', () => {
        expect(field({ kind: 'opens' }).vm.type).toBe('immediately');
        expect(field({ kind: 'opens' }).vm.hint).toBe('Opens as soon as the event is published');
        expect(field().vm.type).toBe('event_end');
        expect(field().vm.hint).toBe('Stays open until the event ends');
    });

    it('offers counting back from the end only for closing', () => {
        expect(field({ kind: 'opens' }).vm.types.map(type => type.value)).toEqual(['immediately', 'start', 'fixed']);
        expect(field().vm.types.map(type => type.value)).toEqual(['event_end', 'start', 'end', 'fixed']);
    });

    it.each([
        ['before the start', rel('start', 2, 'hours'), {}, 'Closes Tue, Nov 10, 2026 · 8:00 AM'],
        ['before the end', rel('end', 30, 'minutes'), {}, 'Closes Tue, Nov 10, 2026 · 11:30 AM'],
        ['zero is the start itself', rel('start', 0, 'hours'), {}, 'Closes Tue, Nov 10, 2026 · 10:00 AM'],
        ['days across a month and a year', rel('start', 10, 'days'), { start: '2027-01-02 09:00' }, 'Closes Wed, Dec 23, 2026 · 9:00 AM'],
        ['a 24-hour clock', rel('start', 3, 'hours'), { timeFormat: 'HH:mm' }, 'Closes Tue, Nov 10, 2026 · 07:00'],
        // Same wall-clock time a week earlier, as the server counts it, though New York's clocks go back in between.
        ['days across a DST change', rel('start', 7, 'days'), { start: '2026-11-03 10:00' }, 'Closes Tue, Oct 27, 2026 · 10:00 AM']
    ])('previews a limit %s', (label, modelValue, props, expected) => {
        expect(field({ modelValue, ...props }).vm.hint).toBe(expected);
    });

    it('says a fixed date stays put', () => {
        expect(field({ modelValue: { type: 'fixed', value: null, unit: null, at: '2026-11-01 09:00:00' } }).vm.hint).toBe('Stays on this date if the event moves');
    });

    // 3:30 AM less two hours on the event's clock is 1:30 AM; a New York browser, whose clocks skip 2 AM that
    // night, would count real hours and preview 12:30 AM.
    it("previews hours on the event's clock, whatever the browser's own DST does", () => {
        const tz = process.env.TZ;
        process.env.TZ = 'America/New_York';

        try {
            const hint = field({ start: '2027-03-14 03:30', modelValue: rel('start', 2, 'hours') }).vm.hint;
            expect(hint).toBe('Closes Sun, Mar 14, 2027 · 1:30 AM');
        } finally {
            process.env.TZ = tz;
        }
    });

    it('emits the full limit, keeping only what its type uses', async () => {
        // As v-model does: each emitted limit comes back as the new value.
        const wrapper = field({ modelValue: rel('end', 2, 'hours'), 'onUpdate:modelValue': limit => wrapper.setProps({ modelValue: limit }) });

        for (const step of [() => wrapper.vm.setUnit('days'), () => wrapper.vm.setType('fixed'), () => wrapper.vm.setAt('2026-11-09 17:00:00'), () => wrapper.vm.setType('event_end')]) {
            step();
            await wrapper.vm.$nextTick();
        }

        expect(wrapper.emitted('update:modelValue')).toEqual([
            [rel('end', 2, 'days')],
            [{ type: 'fixed', value: null, unit: null, at: null }],
            [{ type: 'fixed', value: null, unit: null, at: '2026-11-09 17:00:00' }],
            [{ type: 'event_end', value: null, unit: null, at: null }]
        ]);
    });

    it('fills in 1 when a relative type is picked with no number yet', () => {
        const wrapper = field({ kind: 'opens' });

        wrapper.vm.setType('start');

        expect(wrapper.emitted('update:modelValue')).toEqual([[rel('start', 1, 'days')]]);
    });

    it('takes the saved limit once the edit form loads the event', async () => {
        const wrapper = field({ kind: 'opens' });

        await wrapper.setProps({ modelValue: rel('start', 30, 'minutes') });

        expect([wrapper.vm.value, wrapper.vm.unit]).toEqual([30, 'minutes']);
    });
});
