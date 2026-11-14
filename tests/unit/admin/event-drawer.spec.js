import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import Rest from '@/utils/http/Rest.js';
import main from '@/mixins/main';
import Skeleton from '@/components/Skeleton.vue';
import ErrorState from '@/components/ErrorState.vue';
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
    global: {
        mixins: [main],
        stubs: {
            Drawer: { template: '<div><slot /></div>' },
            ElResult: { template: '<div><slot name="extra" /></div>' },
            ElButton: { props: ['to'], emits: ['click'], template: '<button :data-to="to && JSON.stringify(to)" @click="$emit(\'click\')"><slot /></button>' }
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

    describe('shortcuts', () => {
        const loaded = (status, calendarStatus) => ({
            event: { id: 1, status, calendar: { status: calendarStatus }, description: '' },
            public_url: 'https://site.test/events/meetup/'
        });
        const shortcuts = wrapper => wrapper.findAll('.fe-event-detail__shortcuts button').map(button => button.text());

        it('opens and copies the public page of a public or unlisted event in a public calendar', async () => {
            const writeText = vi.fn().mockResolvedValue();
            Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
            Rest.get.mockResolvedValue(loaded('unlisted', 'public'));

            const wrapper = drawer();
            await flushPromises();

            expect(shortcuts(wrapper)).toEqual(['Edit Event', 'Public Page', 'Copy Link']);
            const [, open, copy] = wrapper.findAll('.fe-event-detail__shortcuts button');
            expect(open.attributes()).toMatchObject({ href: 'https://site.test/events/meetup/', target: '_blank' });

            await copy.trigger('click');
            expect(writeText).toHaveBeenCalledWith('https://site.test/events/meetup/');
        });

        it.each([
            ['a private event', 'private', 'public'],
            ['a draft', 'draft', 'public'],
            ['an event in a private calendar', 'public', 'private']
        ])('offers only Edit for %s, which has no public page', async (_, status, calendarStatus) => {
            Rest.get.mockResolvedValue(loaded(status, calendarStatus));

            const wrapper = drawer();
            await flushPromises();

            expect(shortcuts(wrapper)).toEqual(['Edit Event']);
        });

        it('links the full page and the edit page, so either opens in a new tab too, and closes on a click', async () => {
            Rest.get.mockResolvedValue(loaded('public', 'public'));

            const wrapper = drawer();
            await flushPromises();

            const view = wrapper.find('.fe-event-detail__action');
            const edit = wrapper.find('.fe-event-detail__shortcuts button');
            expect(JSON.parse(view.attributes('data-to'))).toEqual({ name: 'event_detail', params: { id: 1 } });
            expect(JSON.parse(edit.attributes('data-to'))).toEqual({ name: 'event_edit', params: { id: 1 } });

            await edit.trigger('click');
            expect(wrapper.emitted('update:modelValue')).toEqual([[false]]);
        });
    });
});
