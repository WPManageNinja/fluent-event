import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { shallowMount, flushPromises } from '@vue/test-utils';
import { ElNotification } from 'element-plus';
import main from '@/mixins/main';
import Rest from '@/utils/http/Rest.js';
import ResponseProxyItr from '@/utils/http/ResponseProxyItr';
import CreateOrUpdateEvent from '@/modules/events/CreateOrUpdateEvent.vue';

vi.mock('element-plus', async () => ({ ...(await vi.importActual('element-plus')), ElNotification: vi.fn() }));

vi.mock('@/utils/http/Rest.js', () => ({
    default: { get: vi.fn(() => new Promise(() => {})), post: vi.fn(), patch: vi.fn() }
}));

const form = ({ params = {}, query = {} } = {}) => {
    const requestServer = vi.fn();
    const router = { push: vi.fn(), back: vi.fn() };
    const wrapper = shallowMount(CreateOrUpdateEvent, {
        global: {
            mixins: [main, { methods: { $requestServer: requestServer, $changeTitle: vi.fn() } }],
            mocks: { $route: { params, query }, $router: router }
        }
    });

    return { wrapper, vm: wrapper.vm, requestServer, router, request: (path) => requestServer.mock.calls.find(([req]) => req.path === path)?.[0] };
};

const filled = (vm, values = {}) => Object.assign(vm.event, {
    title: 'Community meetup',
    calendar_id: 3,
    start_date: '2026-10-20',
    start_time: '18:00',
    end_date: '2026-10-20',
    end_time: '20:00',
    venue_type: 'offline',
    online_meeting_url: '',
    ...values
});

beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-14T09:20:00Z'));
});

afterEach(() => {
    vi.useRealTimers();
});

describe('CreateOrUpdateEvent', () => {
    describe('a new event', () => {
        it('starts at the next full hour and runs for an hour', () => {
            const { vm } = form();

            expect(vm.event).toMatchObject({
                start_date: '2026-10-14',
                start_time: '10:00',
                end_date: '2026-10-14',
                end_time: '11:00'
            });
        });

        it('defaults to a public RSVP event in the site timezone', () => {
            window.fluentEventAdmin.timezone = 'Asia/Dhaka';
            const { vm } = form();

            expect(vm.event).toMatchObject({ status: 'public', entry_type: 'rsvp', show_guest_count: 'yes', timezone: 'Asia/Dhaka' });
        });

        it("uses the browser's timezone when the site's is not a real zone", () => {
            window.fluentEventAdmin.timezone = 'Mars/Olympus_Mons';

            expect(form().vm.event.timezone).toBe(Intl.DateTimeFormat().resolvedOptions().timeZone);
        });

        it('preselects the calendar named in the URL', () => {
            const { vm, request } = form({ query: { calendar_id: '3' } });

            request('calendars/options').onSuccess({ calendars: [{ id: 2 }, { id: 3 }] });

            expect(vm.event.calendar_id).toBe(3);
        });

        it('falls back to the first calendar when the URL names none or a missing one', () => {
            const { vm, request } = form({ query: { calendar_id: '99' } });

            request('calendars/options').onSuccess({ calendars: [{ id: 2 }, { id: 3 }] });

            expect(vm.event.calendar_id).toBe(2);
        });
    });

    describe('editing', () => {
        it('loads the stored event into the form', () => {
            const { vm, request } = form({ params: { id: '12' } });

            request('events/12').onSuccess({
                event: {
                    calendar_id: 3,
                    status: 'private',
                    title: 'Book club',
                    description: '<p>Chapter 4</p>',
                    start_at_local: '2026-11-02 19:30:00',
                    end_at_local: '2026-11-02 21:00:00',
                    timezone: 'Europe/Berlin',
                    venue_type: 'online',
                    meta: { online_meeting_url: 'https://meet.example.test/x', entry_type: 'open', show_guest_count: 'no' }
                }
            });

            expect(vm.event).toMatchObject({
                calendar_id: 3,
                status: 'private',
                start_date: '2026-11-02',
                start_time: '19:30',
                end_time: '21:00',
                timezone: 'Europe/Berlin',
                online_meeting_url: 'https://meet.example.test/x',
                entry_type: 'open',
                show_guest_count: 'no'
            });
        });

        it('keeps the stored calendar instead of preselecting one', () => {
            const { vm, request } = form({ params: { id: '12' }, query: { calendar_id: '2' } });
            vm.event.calendar_id = 3;

            request('calendars/options').onSuccess({ calendars: [{ id: 2 }, { id: 3 }] });

            expect(vm.event.calendar_id).toBe(3);
        });
    });

    describe('dates', () => {
        it('pulls the end date forward when the start moves past it', () => {
            const { vm } = form();
            filled(vm, { start_date: '2026-10-25' });

            vm.syncEndDate();

            expect(vm.event.end_date).toBe('2026-10-25');
        });
    });

    describe('saving', () => {
        it('posts a new event with full timestamps and opens it', () => {
            const { vm, router, request } = form();
            filled(vm);

            vm.saveEvent();
            const save = request('events');

            expect(save.method).toBe('post');
            expect(save.payload).toMatchObject({ start_at: '2026-10-20 18:00:00', end_at: '2026-10-20 20:00:00', calendar_id: 3 });

            save.onSuccess({ event: { id: 55 } });
            expect(router.push).toHaveBeenCalledWith({ name: 'event_detail', params: { id: 55 } });
        });

        it('patches an existing event in place', () => {
            const { vm, router, requestServer } = form({ params: { id: '12' } });
            filled(vm);

            vm.saveEvent();
            const save = requestServer.mock.calls.at(-1)[0];

            expect(save).toMatchObject({ method: 'patch', path: 'events/12' });

            save.onSuccess({ event: { id: 12 } });
            expect(router.push).toHaveBeenCalledWith({ name: 'event_detail', params: { id: '12' } });
        });

        // Opened from the events list, saving returns there instead of the event's own page.
        it('returns to the page the form was opened from', () => {
            window.history.replaceState({ back: '/events' }, '');
            const { vm, router, requestServer } = form({ params: { id: '12' } });
            filled(vm);

            vm.saveEvent();
            requestServer.mock.calls.at(-1)[0].onSuccess({ event: { id: 12 } });
            window.history.replaceState(null, '');

            expect(router.back).toHaveBeenCalled();
            expect(router.push).not.toHaveBeenCalled();
        });

        it('leaves every check to the server', () => {
            const { vm, request } = form();
            filled(vm, { title: '', calendar_id: null, end_time: '17:00', venue_type: 'online' });

            vm.saveEvent();

            expect(request('events').payload).toMatchObject({ title: '', calendar_id: null, end_at: '2026-10-20 17:00:00' });
        });

        describe('when the server rejects it', () => {
            // The real $requestServer and form items, so the toast comes from the path the page takes.
            const rejected = async (status, responseJSON) => {
                ElNotification.mockClear();
                Rest.post.mockRejectedValueOnce(new ResponseProxyItr({ status, responseJSON }));
                const wrapper = shallowMount(CreateOrUpdateEvent, {
                    global: {
                        stubs: {
                            transition: false, ElForm: false, ElFormItem: false,
                            // The real calendar load renders the form, and create mode focuses the title.
                            ElInput: { template: '<input />', methods: { focus: vi.fn() } }
                        },
                        mixins: [main, { methods: { $changeTitle: vi.fn() } }],
                        mocks: { $route: { params: {}, query: {} }, $router: { push: vi.fn() } }
                    }
                });
                filled(wrapper.vm);

                wrapper.vm.saveEvent();
                await flushPromises();

                return { calls: ElNotification.mock.calls, wrapper };
            };

            it('toasts every validation message instead of marking fields', async () => {
                const { calls, wrapper } = await rejected(422, {
                    title: { required: 'Event title is required.' },
                    end_at: { after: 'End must be after the start.' }
                });

                expect(calls).toHaveLength(1);
                expect(calls[0][0]).toMatchObject({ type: 'error', message: 'Event title is required. End must be after the start.' });
                expect(wrapper.find('[data-field="title"]').exists()).toBe(true);
                expect(wrapper.findAll('.is-error')).toHaveLength(0);
            });

            it.each([
                [404, { message: 'No query results for model [Event] 12' }],
                [403, { code: 'rest_forbidden', message: 'Sorry, you are not allowed to do that.', data: { status: 403 } }],
                [422, { code: 'event_post_update_failed', message: 'The event could not be saved. Please try again.', data: [] }]
            ])('toasts the server message for a %i that is not a validation error', async (status, responseJSON) => {
                const { calls } = await rejected(status, responseJSON);

                expect(calls.at(-1)[0].message).toBe(responseJSON.message);
            });
        });
    });
});
