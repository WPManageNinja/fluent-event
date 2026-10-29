import { describe, it, expect } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import VenueSelect from '@/components/VenueSelect.vue';
import { fullAddress, mapUrl } from '@/utils/venue';

const kingsway = { id: 4, name: 'Kingsway Hall', address: '12 Kingsway', city: 'London', zip_code: 'WC2B 6XF', country: 'United Kingdom', map_url: 'https://maps.example.com/k' };

const select = (modelValue = null) => shallowMount(VenueSelect, {
    props: { modelValue, venues: [kingsway], id: 'fe-test-venue' },
    global: { mixins: [main], stubs: { 'el-form-item': { template: '<div><slot /></div>' } } }
});

describe('venue helpers', () => {
    it('joins the address parts and skips the empty ones', () => {
        expect(fullAddress(kingsway)).toBe('12 Kingsway, London WC2B 6XF, United Kingdom');
        expect(fullAddress({ address: '1 High Street', zip_code: 'LS1' })).toBe('1 High Street, LS1');
        expect(fullAddress()).toBe('');
    });

    it('keeps only an http or https map link', () => {
        expect(mapUrl(kingsway)).toBe('https://maps.example.com/k');
        expect(mapUrl({ map_url: 'javascript:alert(1)' })).toBe('');
    });
});

describe('VenueSelect', () => {
    it('knows the picked venue, and none for an id not in the list', () => {
        expect(select(4).vm.selected).toEqual(kingsway);
        expect(select(99).vm.selected).toBeNull();
    });

    it('emits null, not an empty string, once cleared', () => {
        const wrapper = select(4);

        wrapper.vm.pick('');

        expect(wrapper.emitted('update:modelValue')).toEqual([[null]]);
    });
});
