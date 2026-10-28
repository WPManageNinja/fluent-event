import { describe, it, expect, vi, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import GuestBulkModal from '@/modules/events/parts/GuestBulkModal.vue';

vi.mock('@/utils/http/Rest.js', () => ({ default: { post: vi.fn() } }));

const modal = (bulk, guestIds = [4, 9]) => {
    const requestServer = vi.fn();
    const wrapper = shallowMount(GuestBulkModal, {
        props: { modelValue: true, eventId: 12, guestIds, bulk },
        global: { mixins: [main, { methods: { $requestServer: requestServer } }] }
    });

    return { wrapper, vm: wrapper.vm, requestServer };
};

const notifications = (confirmation, cancellation, waitlist = false) => {
    window.fluentEventAdmin.email_notifications = {
        registration_confirmation: { enabled: confirmation },
        registration_cancellation: { enabled: cancellation },
        registration_waitlist: { enabled: waitlist }
    };
};

afterEach(() => {
    delete window.fluentEventAdmin.email_notifications;
});

describe('GuestBulkModal', () => {
    it('posts the selected guests and the new status to the bulk endpoint', () => {
        notifications(true, true);
        const { vm, requestServer } = modal({ action: 'change_status', status: 'going', label: 'Going' });

        vm.sendEmail = true;
        vm.apply();

        expect(requestServer.mock.calls[0][0]).toMatchObject({
            method: 'post',
            path: 'events/12/guests/bulk-action',
            payload: { action: 'change_status', status: 'going', guest_ids: [4, 9], send_email: true }
        });
    });

    it('offers the email that matches the new status, and only when that email is switched on', () => {
        notifications(true, false);

        expect(modal({ action: 'change_status', status: 'going', label: 'Going' }).vm.emailLabel).toBe('Send confirmation emails');
        expect(modal({ action: 'change_status', status: 'cancelled', label: 'Cancelled' }).vm.emailLabel).toBe('');
        expect(modal({ action: 'change_status', status: 'waitlisted', label: 'Waitlisted' }).vm.emailLabel).toBe('');
        expect(modal({ action: 'change_status', status: 'checked_in', label: 'Checked In' }).vm.emailLabel).toBe('');
    });

    it('offers the waitlist email when guests move to the waitlist', () => {
        notifications(false, false, true);

        expect(modal({ action: 'change_status', status: 'waitlisted', label: 'Waitlisted' }).vm.emailLabel).toBe('Send waitlist emails');
    });

    it('never sends an email the modal did not offer', () => {
        notifications(false, false);
        const { vm, requestServer } = modal({ action: 'change_status', status: 'going', label: 'Going' });

        vm.sendEmail = true;
        vm.apply();

        expect(requestServer.mock.calls[0][0].payload.send_email).toBe(false);
    });

    it('asks before deleting and counts the guests in the title', () => {
        notifications(true, true);
        const { vm } = modal({ action: 'delete' }, [4, 9, 11]);

        expect(vm.title).toBe('Delete 3 Guests');
        expect(vm.emailLabel).toBe('');
        expect(vm.description).toBe('The selected guests will be removed from this event. This cannot be undone.');
    });
});
