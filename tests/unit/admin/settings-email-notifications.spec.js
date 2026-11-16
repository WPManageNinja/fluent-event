import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import main from '@/mixins/main';
import EmailNotifications from '@/modules/settings/email-notifications.vue';

vi.mock('@/utils/http/Rest.js', () => ({
    default: { get: vi.fn(() => new Promise(() => {})), post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() }
}));

const page = () => mount(EmailNotifications, {
    global: {
        mixins: [main, { methods: { $requestServer: vi.fn(), $changeTitle: vi.fn() } }],
        // The real number box renders through ElInput, so both stay real.
        stubs: {
            ElForm: { template: '<form><slot /></form>' },
            ElFormItem: { template: '<div><slot /></div>' },
            ElInput: false
        }
    }
});

// The server only takes a whole number of at least 1, so the box mustn't hold anything it would reject.
describe('EmailNotifications reminder time', () => {
    it.each([['1.5', 2], ['0', 1], ['-2', 1]])('turns %s into %d before Save', async (typed, kept) => {
        const wrapper = page();
        const input = wrapper.find('.fe-form__number input');

        await input.setValue(typed);
        await input.trigger('change');

        expect(wrapper.vm.times[0].value).toBe(kept);
    });
});
