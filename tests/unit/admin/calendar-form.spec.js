import { describe, it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import CreateOrUpdateCalendar from '@/modules/calendars/CreateOrUpdateCalendar.vue';

vi.mock('@/utils/http/Rest.js', () => ({
    default: { get: vi.fn(() => new Promise(() => {})), post: vi.fn(), patch: vi.fn() }
}));

const form = (params = {}) => {
    const requestServer = vi.fn();
    const notifyError = vi.fn();
    const wrapper = shallowMount(CreateOrUpdateCalendar, {
        global: {
            mixins: [main, { methods: { $requestServer: requestServer, $changeTitle: vi.fn(), $notifyError: notifyError } }],
            mocks: { $route: { params, query: {} }, $router: { push: vi.fn() } }
        }
    });

    return { vm: wrapper.vm, requestServer, notifyError };
};

// A calendar made through the API or FluentCommunity gets the server's default colour; the form must agree.
describe('CreateOrUpdateCalendar colour', () => {
    it('starts a new calendar on the server default colour', () => {
        window.fluentEventAdmin.calendar_default_color = '#0099ff';

        expect(form().vm.calendar.theme_color).toBe('#0099ff');
    });

    it('shows the server default for a saved calendar that has no colour', () => {
        window.fluentEventAdmin.calendar_default_color = '#0099ff';
        const { vm, requestServer } = form({ id: '4' });

        requestServer.mock.calls[0][0].onSuccess({ calendar: { title: 'Meetups', slug: 'meetups', status: 'public', meta: {} } });

        expect(vm.calendar.theme_color).toBe('#0099ff');
    });

    // Element Plus emits its clear value (undefined) for picker text it can't parse.
    it('adds a custom hex colour but never an invalid one', () => {
        window.fluentEventAdmin.calendar_default_color = '#0099ff';
        const { vm, notifyError } = form();
        const swatches = vm.colorOptions.length;

        vm.selectColor(undefined);
        vm.selectColor('#zzzzzz');

        expect(vm.colorOptions).toHaveLength(swatches);
        expect(vm.calendar.theme_color).toBe('#0099ff');
        expect(notifyError).toHaveBeenCalledTimes(2);

        vm.selectColor('#123abc');

        expect(vm.colorOptions).toHaveLength(swatches + 1);
        expect(vm.calendar.theme_color).toBe('#123abc');
    });
});
