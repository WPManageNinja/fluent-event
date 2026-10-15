import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import CreateOrUpdateEvent from '@/modules/events/CreateOrUpdateEvent.vue';

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

    describe('validation before saving', () => {
        it('lists every problem in page order and sends nothing', () => {
            const { vm, requestServer } = form();
            filled(vm, { title: '   ', calendar_id: null, end_time: '17:00', venue_type: 'online' });
            const calls = requestServer.mock.calls.length;

            vm.saveEvent();

            expect(vm.errorList.map(error => error.field)).toEqual(['title', 'calendar_id', 'end_at', 'online_meeting_url']);
            expect(requestServer.mock.calls.length).toBe(calls);
        });

        it('rejects an end equal to the start', () => {
            const { vm } = form();
            filled(vm, { end_time: '18:00' });

            expect(vm.fieldError('end_at')).toBe('End must be after the start.');
        });

        it('accepts an end on a later day even at an earlier hour', () => {
            const { vm } = form();
            filled(vm, { end_date: '2026-10-21', end_time: '01:00' });

            expect(vm.fieldError('end_at')).toBe('');
        });

        it('requires an http(s) meeting link for an online event', () => {
            const { vm } = form();
            filled(vm, { venue_type: 'online', online_meeting_url: 'javascript:alert(1)' });
            expect(vm.fieldError('online_meeting_url')).toBe('Meeting link must be a valid http or https URL.');

            vm.event.online_meeting_url = '  https://meet.example.test/abc  ';
            expect(vm.fieldError('online_meeting_url')).toBe('');
        });

        it('ignores the meeting link for an in-person event', () => {
            const { vm } = form();
            filled(vm, { venue_type: 'offline', online_meeting_url: 'not a url' });

            expect(vm.fieldError('online_meeting_url')).toBe('');
        });

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

        it('shows the first server message for each rejected field', () => {
            const { vm, requestServer } = form();
            filled(vm);

            vm.saveEvent();
            requestServer.mock.calls.at(-1)[0].onError({
                errors: { title: { required: 'Title is required.', max: 'Too long.' }, venue: { string: 'Venue must be text.' } }
            });

            expect(vm.errorList).toEqual([
                { field: 'title', message: 'Title is required.' },
                { field: 'venue', message: 'Venue must be text.' }
            ]);
        });
    });
});
