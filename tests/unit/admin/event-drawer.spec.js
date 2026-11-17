import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import Rest from '@/utils/http/Rest.js';
import main from '@/mixins/main';
import Skeleton from '@/components/Skeleton.vue';
import ErrorState from '@/components/ErrorState.vue';
import EventDrawer from '@/modules/events/parts/EventDrawer.vue';
import EventShareModal from '@/modules/events/parts/EventShareModal.vue';

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
    global: {
        mixins: [main],
        stubs: {
            Drawer: { template: '<div><slot /></div>' },
            ElResult: { template: '<div><slot name="extra" /></div>' },
            ElButton: { props: ['to', 'disabled'], emits: ['click'], template: '<button :data-to="to && JSON.stringify(to)" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
            EventShareModal: true
        }
    }
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

    it('says a deleted event no longer exists and tells the list to drop it', async () => {
        Rest.get.mockRejectedValue({ status: 404, message: 'No query results for model [FluentEvent\\App\\Models\\Event] 1' });

        const wrapper = drawer();
        await flushPromises();

        const error = wrapper.findComponent(ErrorState);
        expect(error.props('title')).toBe('This event no longer exists');
        expect(wrapper.find('.fe-event-detail').exists()).toBe(false);
        expect(wrapper.emitted('missing')).toHaveLength(1);
        expect(wrapper.text()).not.toContain('No query results');

        const action = error.find('button');
        expect(action.text()).toBe('Close');
        await action.trigger('click');
        expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
    });

    it('offers a retry when the event fails to load for any other reason', async () => {
        Rest.get
            .mockRejectedValueOnce({ status: 500, message: 'Server error' })
            .mockResolvedValueOnce({ event: { id: 1, description: '<p>Bring snacks</p>' } });

        const wrapper = drawer();
        await flushPromises();

        const error = wrapper.findComponent(ErrorState);
        expect(error.props('message')).toBe('Could not load this event. Please try again.');
        expect(wrapper.find('.fe-event-detail').exists()).toBe(false);
        expect(wrapper.emitted('missing')).toBeUndefined();

        const action = error.find('button');
        expect(action.text()).toBe('Try Again');
        await action.trigger('click');
        await flushPromises();

        expect(wrapper.findComponent(ErrorState).exists()).toBe(false);
        expect(wrapper.find('.fe-event-detail__description').html()).toContain('<p>Bring snacks</p>');
    });

    describe('share and actions', () => {
        const loaded = (status, calendarStatus) => ({
            event: { id: 1, status, calendar: { status: calendarStatus }, description: '' },
            public_url: 'https://site.test/events/meetup/',
            shortcode: '[fluent_event event="meetup"]'
        });
        const shareButton = wrapper => wrapper.findAll('.fe-event-detail__actions button').find(button => button.text() === 'Share');

        // The list's copy of the event has no calendar status, so the modal waits for the loaded one.
        it('hands the loaded event, its link and shortcode to the share modal', async () => {
            const request = deferred();
            Rest.get.mockReturnValue(request.promise);

            const wrapper = drawer();
            expect(shareButton(wrapper).attributes('disabled')).toBeDefined();
            expect(wrapper.findComponent(EventShareModal).exists()).toBe(false);

            request.resolve(loaded('unlisted', 'public'));
            await flushPromises();
            await shareButton(wrapper).trigger('click');

            const modal = wrapper.findComponent(EventShareModal);
            expect(modal.props()).toMatchObject({
                modelValue: true,
                event: { id: 1, status: 'unlisted', calendar: { status: 'public' } },
                publicUrl: 'https://site.test/events/meetup/',
                shortcode: '[fluent_event event="meetup"]'
            });
        });

        it('links the full page and the edit page, so either opens in a new tab too, and closes on a click', async () => {
            Rest.get.mockResolvedValue(loaded('public', 'public'));

            const wrapper = drawer();
            await flushPromises();

            const [view, edit] = wrapper.findAll('.fe-event-detail__actions button');
            expect(view.text()).toBe('View Full Details');
            expect(edit.text()).toBe('Edit Event');
            expect(JSON.parse(view.attributes('data-to'))).toEqual({ name: 'event_detail', params: { id: 1 } });
            expect(JSON.parse(edit.attributes('data-to'))).toEqual({ name: 'event_edit', params: { id: 1 } });

            await edit.trigger('click');
            expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
        });
    });
});
