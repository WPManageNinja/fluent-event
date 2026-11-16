import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import main from '@/mixins/main';
import SettingsGeneral from '@/modules/settings/general.vue';

vi.mock('@/utils/http/Rest.js', () => ({
    default: { get: vi.fn(() => new Promise(() => {})), post: vi.fn(), put: vi.fn(), patch: vi.fn(), del: vi.fn() }
}));

const saved = { id: 10, title: 'Saved page', link: 'https://example.test/saved/', has_shortcode: false };
const listed = { id: 20, title: 'Listed page', link: 'https://example.test/listed/', has_shortcode: true };
const plain = { id: 30, title: 'Plain page', link: 'https://example.test/plain/', has_shortcode: false };

const page = () => {
    const requestServer = vi.fn();
    const wrapper = mount(SettingsGeneral, {
        global: {
            mixins: [main, { methods: { $requestServer: requestServer, $changeTitle: vi.fn() } }],
            stubs: {
                ElForm: { template: '<form><slot /></form>' },
                ElFormItem: { template: '<div><slot /></div>' },
                ElSelect: { name: 'ElSelect', emits: ['update:modelValue', 'visible-change'], template: '<div />' }
            }
        }
    });
    const respond = (path, response) => requestServer.mock.calls.filter(([options]) => options.path === path).pop()[0].onSuccess(response);

    respond('settings/general', {
        settings: { default_events_view: 'timeline', disable_event_view_switch: 'no', events_page_id: 10, event_slug: 'event' },
        pages: { events_page_id: saved }
    });
    // The mocked request leaves the loading flag to its caller.
    wrapper.vm.generalLoading = false;

    return { wrapper, respond };
};

// Picking a page doesn't save it, so the hints under the select must follow the pick, not the saved page.
describe('SettingsGeneral page picker', () => {
    it('points Preview and the missing-block warning at the picked page', async () => {
        const { wrapper, respond } = page();
        await wrapper.vm.$nextTick();
        const select = wrapper.findComponent({ name: 'ElSelect' });
        const preview = () => wrapper.find('.fe-page-picker__help a');
        const warning = () => wrapper.find('.fe-page-picker__warning');

        expect(preview().attributes('href')).toBe(saved.link);
        expect(warning().exists()).toBe(true);

        select.vm.$emit('visible-change', true);
        respond('settings/pages', { pages: [saved, listed, plain] });
        select.vm.$emit('update:modelValue', listed.id);
        await wrapper.vm.$nextTick();

        expect(preview().attributes('href')).toBe(listed.link);
        expect(warning().exists()).toBe(false);

        select.vm.$emit('update:modelValue', plain.id);
        await wrapper.vm.$nextTick();

        expect(preview().attributes('href')).toBe(plain.link);
        expect(warning().exists()).toBe(true);

        select.vm.$emit('update:modelValue', undefined);
        await wrapper.vm.$nextTick();

        expect(preview().exists()).toBe(false);
        expect(warning().exists()).toBe(false);
    });
});
