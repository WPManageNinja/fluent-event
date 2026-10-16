import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import Rest from '@/utils/http/Rest.js';
import main from '@/mixins/main';
import Skeleton from '@/components/Skeleton.vue';
import EventDrawer from '@/modules/events/parts/EventDrawer.vue';

vi.mock('@/utils/http/Rest.js', () => ({
    default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() }
}));

const deferred = () => {
    let resolve;
    const promise = new Promise(res => {
        resolve = res;
    });
    return { promise, resolve };
};

const event = (id) => ({
    id,
    title: 'Meetup',
    status: 'public',
    timezone: 'UTC',
    start_at_local: '2026-10-20 10:00:00',
    end_at_local: '2026-10-20 11:00:00',
    start_at_site: '2026-10-20 10:00:00',
    end_at_site: '2026-10-20 11:00:00',
    meta: {}
});

const drawer = () => mount(EventDrawer, {
    props: { modelValue: true, event: event(1) },
    global: { mixins: [main], stubs: { Drawer: { template: '<div><slot /></div>' } } }
});

beforeEach(() => {
    Rest.get.mockReset();
});

// The event list leaves descriptions out, so the drawer loads the one it shows.
describe('EventDrawer', () => {
    it('shows a skeleton until the description of the opened event arrives', async () => {
        const request = deferred();
        Rest.get.mockReturnValue(request.promise);

        const wrapper = drawer();

        expect(Rest.get).toHaveBeenCalledWith('events/1', undefined);
        expect(wrapper.findComponent(Skeleton).exists()).toBe(true);

        request.resolve({ event: { id: 1, description: '<p>Bring snacks</p>' } });
        await flushPromises();

        expect(wrapper.findComponent(Skeleton).exists()).toBe(false);
        expect(wrapper.find('.fe-event-detail__description').html()).toContain('<p>Bring snacks</p>');
    });

    it('drops a description that arrives after another event was opened', async () => {
        const first = deferred();
        const second = deferred();
        Rest.get.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise);

        const wrapper = drawer();
        await wrapper.setProps({ event: event(2) });

        second.resolve({ event: { id: 2, description: '<p>New</p>' } });
        await flushPromises();
        first.resolve({ event: { id: 1, description: '<p>Old</p>' } });
        await flushPromises();

        expect(wrapper.find('.fe-event-detail__description').html()).toContain('<p>New</p>');
        expect(wrapper.find('.fe-event-detail__description').html()).not.toContain('<p>Old</p>');
    });
});
