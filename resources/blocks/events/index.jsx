import { Icon, PATHS } from '../event-parts/index.jsx';

const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, SelectControl, ToggleControl } = wp.components;

const VIEW_LABELS = {
    cards: __('Cards', 'fluent-event'),
    timeline: __('Timeline', 'fluent-event'),
    calendar: __('Calendar', 'fluent-event'),
};

// The editor never shows real events: made-up calendars and events in the page's own markup, so a page can be laid
// out before any event exists. Each calendar's colour is a tint class (blocks.scss).
const SAMPLE_CALENDARS = [
    { title: __('Workshops', 'fluent-event'), tint: 'a' },
    { title: __('Meetups', 'fluent-event'), tint: 'b' },
    { title: __('Webinars', 'fluent-event'), tint: 'c' },
];

const SAMPLE_EVENTS = [
    { title: __('Hands-on Design Workshop', 'fluent-event'), calendar: 0, inDays: 3, day: 6, time: __('10:00 AM – 12:00 PM', 'fluent-event'), place: __('Community Hall', 'fluent-event') },
    { title: __('Monthly Community Meetup', 'fluent-event'), calendar: 1, inDays: 8, day: 14, time: __('6:00 PM – 8:00 PM', 'fluent-event'), place: __('City Library', 'fluent-event') },
    { title: __('Product Launch Livestream', 'fluent-event'), calendar: 2, inDays: 15, day: 22, time: __('3:00 PM – 4:00 PM', 'fluent-event'), place: __('Online', 'fluent-event'), online: true },
];

function sampleDate(inDays) {
    const date = new Date();
    date.setDate(date.getDate() + inDays);

    return date;
}

function SampleTile({ event, row, showCalendar }) {
    const calendar = SAMPLE_CALENDARS[event.calendar];
    const date = sampleDate(event.inDays);
    const cover = <span className={`fe-event-tile__cover fe-event-tile__cover--empty fe-block-demo__tint--${calendar.tint}`} />;
    const body = (
        <div className="fe-event-tile__body">
            {showCalendar && (
                <span className="fe-event-tile__calendar">
                    <span className={`fe-event-tile__calendar-dot fe-block-demo__tint--${calendar.tint}`} aria-hidden="true" />
                    {calendar.title}
                </span>
            )}
            {!row && <span className="fe-event-tile__date">{date.toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' })}</span>}
            <span className="fe-event-tile__time">{event.time}</span>
            <h3 className="fe-event-tile__title"><span className="fe-event-tile__link">{event.title}</span></h3>
            <span className="fe-event-tile__location"><Icon path={event.online ? PATHS.video : PATHS.pin} />{event.place}</span>
        </div>
    );

    return row
        ? <div className="fe-event-tile fe-event-tile--row">{body}{cover}</div>
        : <li className="fe-event-tile">{cover}{body}</li>;
}

function SampleMonth({ weekStart }) {
    const today = new Date();
    const first = new Date(today.getFullYear(), today.getMonth(), 1);
    const start = new Date(first);
    start.setDate(1 - ((first.getDay() - weekStart + 7) % 7));
    const days = Array.from({ length: 42 }, (_, index) => {
        const day = new Date(start);
        day.setDate(start.getDate() + index);

        return day;
    });

    return (
        <section className="fe-month">
            <div className="fe-month__grid">
                {days.slice(0, 7).map(day => (
                    <span key={`name-${day.getDay()}`} className="fe-month__weekday">{day.toLocaleDateString(undefined, { weekday: 'short' })}</span>
                ))}

                {days.map(day => {
                    const inMonth = day.getMonth() === first.getMonth();
                    const isToday = day.toDateString() === today.toDateString();

                    return (
                        <div key={day.toDateString()} className={`fe-month__cell${inMonth ? '' : ' fe-month__cell--outside'}${isToday ? ' fe-month__cell--today' : ''}`}>
                            <span className="fe-month__day">{day.getDate()}</span>
                            {inMonth && SAMPLE_EVENTS.filter(event => event.day === day.getDate()).map(event => (
                                <span key={event.title} className={`fe-month__chip fe-block-demo__tint--${SAMPLE_CALENDARS[event.calendar].tint}`}>
                                    <span className="fe-month__chip-time">{event.time.split(' – ')[0]}</span>
                                    <span className="fe-month__chip-title">{event.title}</span>
                                </span>
                            ))}
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

function SampleList({ attributes }) {
    const settings = window.fluentEventBlocks || {};
    const view = attributes.view || settings.view || 'timeline';
    const viewLocked = attributes.view_switch ? attributes.view_switch === 'no' : !!settings.view_locked;
    const filter = attributes.filter || 'upcoming';
    const showChips = attributes.show_calendars !== 'no';
    // As on the page: a list fixed to one calendar needs no calendar label on its events.
    const showCalendar = showChips || !attributes.calendar;
    const filters = { all: __('All', 'fluent-event'), upcoming: __('Upcoming', 'fluent-event'), past: __('Past', 'fluent-event') };
    const views = { cards: PATHS.grid, timeline: PATHS.timeline, calendar: PATHS.calendar };

    return (
        <div className="fe-events-page">
            <p className="fe-block-demo__note">{__('Sample events: the page shows your public events here.', 'fluent-event')}</p>

            {showChips && (
                <div className="fe-calendar-chips">
                    <div className="fe-calendar-chips__row">
                        <span className={`fe-calendar-chips__chip${attributes.calendar ? '' : ' fe-calendar-chips__chip--active'}`}>
                            <span className="fe-calendar-chips__label">{__('All', 'fluent-event')}</span>
                        </span>
                        {SAMPLE_CALENDARS.map((calendar, index) => (
                            <span key={calendar.title} className={`fe-calendar-chips__chip${attributes.calendar && index === 0 ? ' fe-calendar-chips__chip--active' : ''}`}>
                                <span className={`fe-calendar-chips__dot fe-block-demo__tint--${calendar.tint}`} aria-hidden="true" />
                                <span className="fe-calendar-chips__label">{calendar.title}</span>
                            </span>
                        ))}
                    </div>
                </div>
            )}

            <div className="fe-events-page__toolbar">
                <div className="fe-events-page__search">
                    <input
                        type="search"
                        className="fe-events-page__search-input"
                        placeholder={__('Search events', 'fluent-event')}
                        aria-label={__('Search events', 'fluent-event')}
                        readOnly
                        tabIndex={-1}
                    />
                </div>

                {view !== 'calendar' && (
                    <div className="fe-switch">
                        {Object.keys(filters).map(key => (
                            <span key={key} className={`fe-switch__option${key === filter ? ' fe-switch__option--active' : ''}`}>{filters[key]}</span>
                        ))}
                    </div>
                )}

                {!viewLocked && (
                    <div className="fe-switch">
                        {Object.keys(views).map(key => (
                            <span key={key} className={`fe-switch__option fe-switch__option--icon${key === view ? ' fe-switch__option--active' : ''}`} title={VIEW_LABELS[key]}>
                                <Icon path={views[key]} />
                            </span>
                        ))}
                    </div>
                )}
            </div>

            {view === 'calendar' && (
                <div className="fe-calendar-head">
                    <h2 className="fe-calendar-head__title">{new Date().toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}</h2>
                    <div className="fe-switch">
                        <span className="fe-switch__option fe-switch__option--active">{__('Month', 'fluent-event')}</span>
                        <span className="fe-switch__option">{__('Week', 'fluent-event')}</span>
                        <span className="fe-switch__option">{__('Day', 'fluent-event')}</span>
                    </div>
                    <div className="fe-calendar-head__nav">
                        <span className="fe-calendar-head__step"><Icon path={PATHS.chevronLeft} /></span>
                        <span className="fe-calendar-head__today">{__('Today', 'fluent-event')}</span>
                        <span className="fe-calendar-head__step"><Icon path={PATHS.chevronRight} /></span>
                    </div>
                </div>
            )}

            <div className="fe-events-page__results">
                {view === 'calendar' && <SampleMonth weekStart={settings.week_start || 0} />}

                {view === 'timeline' && (
                    <ol className="fe-timeline">
                        {SAMPLE_EVENTS.map(event => {
                            const date = sampleDate(event.inDays);

                            return (
                                <li key={event.title} className="fe-timeline__item">
                                    <span className="fe-timeline__stamp">
                                        <span className="fe-timeline__date">{date.toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' })}</span>
                                        <span className="fe-timeline__weekday">{date.toLocaleDateString(undefined, { weekday: 'long' })}</span>
                                    </span>
                                    <SampleTile event={event} row showCalendar={showCalendar} />
                                </li>
                            );
                        })}
                    </ol>
                )}

                {view === 'cards' && (
                    <ul className="fe-event-grid">
                        {SAMPLE_EVENTS.map(event => <SampleTile key={event.title} event={event} showCalendar={showCalendar} />)}
                    </ul>
                )}
            </div>
        </div>
    );
}

function Edit({ attributes, setAttributes }) {
    const { calendars = [] } = window.fluentEventBlocks || {};
    const showCalendars = attributes.show_calendars !== 'no';

    return (
        <div {...useBlockProps({ className: 'fe-portal fe-block-demo' })}>
            <InspectorControls>
                <PanelBody title={__('Events list', 'fluent-event')}>
                    <SelectControl
                        label={__('First calendar', 'fluent-event')}
                        help={showCalendars
                            ? __('The calendar picked first. Visitors can switch with the calendar pills.', 'fluent-event')
                            : __('With the pills hidden, the list stays on this calendar.', 'fluent-event')}
                        value={attributes.calendar}
                        options={[
                            { label: __('All calendars', 'fluent-event'), value: '' },
                            ...calendars.map(item => ({ label: item.title, value: item.slug })),
                        ]}
                        onChange={value => setAttributes({ calendar: value })}
                    />
                    <ToggleControl
                        label={__('Show calendar pills', 'fluent-event')}
                        checked={showCalendars}
                        onChange={checked => setAttributes({ show_calendars: checked ? 'yes' : 'no' })}
                    />
                    <SelectControl
                        label={__('View', 'fluent-event')}
                        value={attributes.view}
                        options={[
                            { label: __('Use the setting', 'fluent-event'), value: '' },
                            ...Object.keys(VIEW_LABELS).map(key => ({ label: VIEW_LABELS[key], value: key })),
                        ]}
                        onChange={value => setAttributes({ view: value })}
                    />
                    <SelectControl
                        label={__('View switcher', 'fluent-event')}
                        value={attributes.view_switch}
                        options={[
                            { label: __('Use the setting', 'fluent-event'), value: '' },
                            { label: __('Show', 'fluent-event'), value: 'yes' },
                            { label: __('Hide', 'fluent-event'), value: 'no' },
                        ]}
                        onChange={value => setAttributes({ view_switch: value })}
                    />
                    <SelectControl
                        label={__('Events shown first', 'fluent-event')}
                        value={attributes.filter}
                        options={[
                            { label: __('Upcoming', 'fluent-event'), value: '' },
                            { label: __('All', 'fluent-event'), value: 'all' },
                            { label: __('Past', 'fluent-event'), value: 'past' },
                        ]}
                        onChange={value => setAttributes({ filter: value })}
                    />
                </PanelBody>
            </InspectorControls>

            <SampleList attributes={attributes} />
        </div>
    );
}

export default function registerEventsBlock() {
    registerBlockType('fluent-event/events', {
        apiVersion: 2,
        title: __('Fluent Events', 'fluent-event'),
        description: __('A list of your public events, as cards, a timeline or a calendar.', 'fluent-event'),
        category: 'widgets',
        icon: 'calendar-alt',
        keywords: [__('events', 'fluent-event'), __('calendar', 'fluent-event'), 'fluent'],
        supports: { align: ['wide', 'full'], html: false },
        attributes: {
            calendar: { type: 'string', default: '' },
            view: { type: 'string', default: '' },
            filter: { type: 'string', default: '' },
            view_switch: { type: 'string', default: '' },
            show_calendars: { type: 'string', default: 'yes' },
        },
        edit: Edit,
        save: () => null,
    });
}
