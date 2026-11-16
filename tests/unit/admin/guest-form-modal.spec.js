import { describe, it, expect } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import GuestFormModal from '@/modules/events/parts/GuestFormModal.vue';

describe('GuestFormModal', () => {
    // A "*" typed into the label is a second string for translators and looks unlike every other form.
    it("marks its required fields with the form's own asterisk, on the right as in FluentCommunity", async () => {
        const wrapper = shallowMount(GuestFormModal, {
            props: { modelValue: true, eventId: 1, statuses: [] },
            global: { mixins: [main], renderStubDefaultSlot: true }
        });
        wrapper.vm.mode = 'wp_user';
        await wrapper.vm.$nextTick();

        const fields = wrapper.findAll('el-form-item-stub')
            .filter(item => item.attributes('label'))
            .map(item => [item.attributes('label'), item.attributes('required') === 'true']);

        expect(wrapper.find('el-form-stub').attributes('requireasteriskposition')).toBe('right');
        expect(fields).toEqual([
            ['WordPress User', true],
            ['Name', true],
            ['Email', true],
            ['Status', false]
        ]);
    });
});
