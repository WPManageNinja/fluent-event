import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import dayjs from 'dayjs';
import main from '@/mixins/main';
import EventsGrid from '@/modules/events/parts/EventsGrid.vue';

let nextId = 1;
const event = (start, end, extra = {}) => ({ id: nextId++, title: 'Event', start_at_site: start, end_at_site: end, ...extra });

const grid = (props = {}) => mount(EventsGrid, {
    props: { events: [], month: '2026-10-14', viewMode: 'week', ...props },
    global: { mixins: [main] }
});

const blockStyle = (wrapper) => wrapper.findAll('.fe-events-grid__week-event').map(block => ({
    top: block.element.style.top,
    height: block.element.style.height
}));

describe('EventsGrid', () => {
    it('places a multi-day event on every day it spans, numbering the days', () => {
        const meetup = event('2026-10-13 22:00:00', '2026-10-15 01:00:00');
        const days = grid({ events: [meetup] }).vm.entriesByDay;

        expect(Object.keys(days)).toEqual(['2026-10-13', '2026-10-14', '2026-10-15']);
        expect(days['2026-10-15']).toEqual([{ event: meetup, dayIndex: 3 }]);
    });

    it('starts an event that began before the grid on its first day, keeping the day count', () => {
        // October 2026's grid opens on Sunday 27 September.
        const retreat = event('2026-09-20 09:00:00', '2026-09-28 17:00:00');
        const days = grid({ events: [retreat] }).vm.entriesByDay;

        expect(Object.keys(days)).toEqual(['2026-09-27', '2026-09-28']);
        expect(days['2026-09-27'][0].dayIndex).toBe(8);
    });

    it("orders a day's events by start time", () => {
        const late = event('2026-10-14 18:00:00', '2026-10-14 19:00:00');
        const early = event('2026-10-14 08:00:00', '2026-10-14 09:00:00');

        expect(grid({ events: [late, early] }).vm.entriesFor('2026-10-14').map(entry => entry.event)).toEqual([early, late]);
    });

    it('shows the start time on the first day and "Day N" after it', () => {
        const wrapper = grid();
        const meetup = event('2026-10-13 18:30:00', '2026-10-14 01:00:00');

        expect(wrapper.vm.entryTime({ event: meetup, dayIndex: 1 })).toBe('6:30 pm');
        expect(wrapper.vm.entryTime({ event: meetup, dayIndex: 2 })).toBe('Day 2');
    });

    it('positions a block by its start and length in the hour grid', () => {
        const wrapper = grid({ events: [event('2026-10-14 09:00:00', '2026-10-14 11:00:00')] });

        // 48px an hour, less a 2px gap.
        expect(blockStyle(wrapper)).toEqual([{ top: '432px', height: '94px' }]);
    });

    it('gives a very short event half an hour of height so it stays clickable', () => {
        const wrapper = grid({ events: [event('2026-10-14 09:00:00', '2026-10-14 09:05:00')] });

        expect(blockStyle(wrapper)).toEqual([{ top: '432px', height: '22px' }]);
    });

    it('cuts an overnight event at midnight on both days', () => {
        const wrapper = grid({ events: [event('2026-10-13 22:00:00', '2026-10-14 02:00:00')] });

        expect(blockStyle(wrapper)).toEqual([
            { top: '1056px', height: '94px' },
            { top: '0px', height: '94px' }
        ]);
    });

    it('shows one column in day view and seven in week view', () => {
        expect(grid({ viewMode: 'day' }).findAll('.fe-events-grid__week-col')).toHaveLength(1);
        expect(grid({ viewMode: 'week' }).findAll('.fe-events-grid__week-col')).toHaveLength(7);
    });

    it('titles the week by its first and last day', () => {
        expect(grid().find('.fe-events-grid__month').text()).toBe('Oct 11 – Oct 17, 2026');
    });

    it('labels the hour rows in the site clock format', () => {
        expect(grid().vm.hourLabel(9)).toBe('9 AM');

        window.fluentEventAdmin.time_format = 'HH:mm';
        expect(grid().vm.hourLabel(9)).toBe('09:00');
    });

    it('steps by the current view unit', async () => {
        const week = grid({ viewMode: 'week' });
        week.vm.moveStep(1);

        const day = grid({ viewMode: 'day' });
        day.vm.moveStep(-1);

        expect(week.emitted('change-month')).toEqual([['2026-10-21']]);
        expect(day.emitted('change-month')).toEqual([['2026-10-13']]);
    });

    it('does not reload when moving to the day already shown', () => {
        const wrapper = grid();
        wrapper.vm.moveTo(dayjs('2026-10-14'));

        expect(wrapper.emitted('change-month')).toBeUndefined();
    });

    it('opens the event behind a clicked block', async () => {
        const meetup = event('2026-10-14 09:00:00', '2026-10-14 10:00:00');
        const wrapper = grid({ events: [meetup] });

        await wrapper.find('.fe-events-grid__week-event').trigger('click');

        expect(wrapper.emitted('open')).toEqual([[meetup]]);
    });

    describe('a phone month grid', () => {
        const pick = async (wrapper, day) => {
            wrapper.vm.pickDay(day);
            await wrapper.vm.$nextTick();
        };

        beforeEach(() => {
            Element.prototype.scrollIntoView = vi.fn();
            window.matchMedia = vi.fn(() => ({ matches: false }));
        });

        it('lists nothing under the grid until a day is tapped', () => {
            expect(grid({ viewMode: 'month' }).find('.fe-events-grid__agenda').exists()).toBe(false);
        });

        it("lists the tapped day's events, or that it has none, and brings the list into view", async () => {
            const meetup = event('2026-10-14 09:00:00', '2026-10-14 10:00:00', { title: 'Meetup' });
            const wrapper = grid({ viewMode: 'month', events: [meetup] });

            await pick(wrapper, '2026-10-14');
            expect(wrapper.find('.fe-events-grid__agenda').text()).toContain('Meetup');
            expect(Element.prototype.scrollIntoView).toHaveBeenCalledWith({ block: 'nearest', behavior: 'smooth' });

            await wrapper.find('.fe-events-grid__agenda-event').trigger('click');
            expect(wrapper.emitted('open')).toEqual([[meetup]]);

            await pick(wrapper, '2026-10-15');
            expect(wrapper.find('.fe-events-grid__agenda-empty').text()).toBe('No events on this day.');
        });

        it('clears the tapped day when the month changes', async () => {
            const wrapper = grid({ viewMode: 'month' });

            await pick(wrapper, '2026-10-14');
            await wrapper.setProps({ month: '2026-11-14' });

            expect(wrapper.find('.fe-events-grid__agenda').exists()).toBe(false);
        });

        it('clears a day from the previous month when the grid moves to that month', async () => {
            const wrapper = grid({ viewMode: 'month' });

            await pick(wrapper, '2026-09-28');
            await wrapper.setProps({ month: '2026-09-14' });

            expect(wrapper.find('.fe-events-grid__agenda').exists()).toBe(false);
        });

        it('keeps the tapped day when the date moves inside the month', async () => {
            const wrapper = grid({ viewMode: 'month' });

            await pick(wrapper, '2026-10-20');
            await wrapper.setProps({ month: '2026-10-20' });

            expect(wrapper.find('.fe-events-grid__agenda').exists()).toBe(true);
        });
    });
});
