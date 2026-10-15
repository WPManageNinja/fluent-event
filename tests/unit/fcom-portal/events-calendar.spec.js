import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { t } from '../../../resources/fcom-portal/utils';
import EventsCalendar from '../../../resources/fcom-portal/app/EventsCalendar.vue';

let nextId = 1;
const event = (start, end) => ({ id: nextId++, title: 'Event', start_at_local: start, end_at_local: end });

const calendar = (props = {}) => mount(EventsCalendar, {
    props: { events: [], date: '2026-10-14', mode: 'day', ...props },
    global: { mocks: { $t: t } }
});

describe('EventsCalendar', () => {
    it('places a multi-day event on each day it spans, numbering the days', () => {
        const retreat = event('2026-10-13 18:00:00', '2026-10-15 12:00:00');
        const days = calendar({ events: [retreat], mode: 'month' }).vm.entriesByDay;

        expect(Object.entries(days).map(([day, entries]) => [day, entries[0].dayIndex])).toEqual([
            ['2026-10-13', 1],
            ['2026-10-14', 2],
            ['2026-10-15', 3]
        ]);
    });

    it('sizes blocks as a share of the day so they follow the CSS row height', () => {
        const wrapper = calendar({ events: [event('2026-10-14 06:00:00', '2026-10-14 12:00:00')] });
        const style = wrapper.find('.fe-events-calendar__week-event').element.style;

        expect(style.top).toBe('25%');
        expect(style.height).toBe('calc(25% - 2px)');
    });

    it("labels the hours in fcom's clock format", () => {
        expect(calendar().find('.fe-events-calendar__week-time:nth-child(2)').text()).toBe('1 AM');

        window.fluentComAdmin.time_formatter = 'HH:mm';
        expect(calendar().find('.fe-events-calendar__week-time:nth-child(2)').text()).toBe('01:00');
    });

    it('shows the start time on the first day and "Day N" after it', () => {
        const wrapper = calendar();
        const retreat = event('2026-10-13 18:30:00', '2026-10-15 12:00:00');

        expect(wrapper.vm.entryTime({ event: retreat, dayIndex: 1 })).toBe('6:30 PM');
        expect(wrapper.vm.entryTime({ event: retreat, dayIndex: 3 })).toBe('Day 3');
    });

    it('titles the day view with the full date and the week view with its range', () => {
        expect(calendar({ mode: 'day' }).vm.titleLabel).toBe('Wednesday, Oct 14, 2026');
        expect(calendar({ mode: 'week' }).vm.titleLabel).toBe('Oct 11 – Oct 17, 2026');
    });

    it('steps by the current mode', () => {
        const week = calendar({ mode: 'week' });
        week.vm.moveStep(-1);

        expect(week.emitted('change-date')).toEqual([['2026-10-07']]);
    });

    it('opens the event behind a clicked block', async () => {
        const meetup = event('2026-10-14 09:00:00', '2026-10-14 10:00:00');
        const wrapper = calendar({ events: [meetup] });

        await wrapper.find('.fe-events-calendar__week-event').trigger('click');

        expect(wrapper.emitted('open')).toEqual([[meetup]]);
    });
});
