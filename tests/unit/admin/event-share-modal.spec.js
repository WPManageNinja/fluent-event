import { describe, it, expect } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import main from '@/mixins/main';
import EventShareModal from '@/modules/events/parts/EventShareModal.vue';

const mount = (status, calendarStatus) => shallowMount(EventShareModal, {
    props: {
        modelValue: true,
        event: { title: 'Launch', status, calendar: { status: calendarStatus } },
        publicUrl: 'https://example.test/event/launch/',
        shortcode: '[fluent_event event="launch"]',
    },
    global: { mixins: [main], renderStubDefaultSlot: true },
});

describe('EventShareModal', () => {
    it.each([
        ['private', 'public', 'Only public and unlisted events have a public page.'],
        ['draft', 'public', 'Only public and unlisted events have a public page.'],
        ['public', 'private', 'Its calendar is not public, so this event has no public page.'],
    ])('gives the reason instead of the share options for a %s event in a %s calendar', (status, calendarStatus, reason) => {
        const wrapper = mount(status, calendarStatus);

        expect(wrapper.find('.fe-share-modal__note').text()).toBe(reason);
        expect(wrapper.find('tabs-stub').exists()).toBe(false);
    });

    it.each(['public', 'unlisted'])('shows the share options for a %s event in a public calendar', status => {
        const wrapper = mount(status, 'public');

        expect(wrapper.find('.fe-share-modal__note').exists()).toBe(false);
        expect(wrapper.find('tabs-stub').exists()).toBe(true);
    });
});
