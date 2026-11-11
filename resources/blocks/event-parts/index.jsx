const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;
const { useBlockProps } = wp.blockEditor;

// Icon::CALENDAR, CLOCK and VIDEO, as the page draws them.
const PATHS = {
    calendar: 'M6.66667 1.66699V4.16699M13.3333 1.66699V4.16699M2.5 7.50033H17.5M4.16667 3.33366H15.8333C16.7538 3.33366 17.5 4.07985 17.5 5.00033V16.667C17.5 17.5875 16.7538 18.3337 15.8333 18.3337H4.16667C3.24619 18.3337 2.5 17.5875 2.5 16.667V5.00033C2.5 4.07985 3.24619 3.33366 4.16667 3.33366Z',
    clock: 'M10 5.83V10L12.5 12.5M17.5 10C17.5 14.14 14.14 17.5 10 17.5C5.86 17.5 2.5 14.14 2.5 10C2.5 5.86 5.86 2.5 10 2.5C14.14 2.5 17.5 5.86 17.5 10Z',
    video: 'M13.33 8.33L17.5 5.83V14.17L13.33 11.67M4.17 15H11.67C12.59 15 13.33 14.25 13.33 13.33V6.67C13.33 5.75 12.59 5 11.67 5H4.17C3.25 5 2.5 5.75 2.5 6.67V13.33C2.5 14.25 3.25 15 4.17 15Z',
};

function Icon({ path }) {
    return (
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d={path} stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

const today = new Date();

// The editor has no open event, so each piece draws a sample in the event page's own markup and classes.
export const demos = {
    cover: () => <div className="fe-event-page__cover fe-block-demo__cover">{__('Cover image', 'fluent-event')}</div>,
    title: () => <h1 className="fe-event-page__title">{__('Event title', 'fluent-event')}</h1>,
    meta: () => (
        <ul className="fe-event-page__meta">
            <li className="fe-event-page__meta-item"><Icon path={PATHS.calendar} /><span>{__('Event date', 'fluent-event')}</span></li>
            <li className="fe-event-page__meta-item"><Icon path={PATHS.clock} /><span>{__('Start – end time', 'fluent-event')}</span></li>
            <li className="fe-event-page__meta-item"><Icon path={PATHS.video} /><span>{__('Online', 'fluent-event')}</span></li>
        </ul>
    ),
    calendar: () => (
        <div className="fe-calendar-chips fe-event-page__calendar-tag">
            <span className="fe-calendar-chips__chip">
                <span className="fe-calendar-chips__dot" aria-hidden="true" />
                <span className="fe-calendar-chips__label">{__('Calendar', 'fluent-event')}</span>
            </span>
        </div>
    ),
    description: () => (
        <section className="fe-event-page__card fe-event-page__body">
            <h2 className="fe-event-page__section-title">{__('About This Event', 'fluent-event')}</h2>
            <div className="fe-event-page__description">
                <p>{__('The event’s description shows here.', 'fluent-event')}</p>
            </div>
        </section>
    ),
    registration: () => (
        <aside className="fe-event-page__card fe-event-page__side">
            <div className="fe-event-page__when">
                <span className="fe-date-chip" aria-hidden="true">
                    <span className="fe-date-chip__month">{today.toLocaleDateString(undefined, { month: 'short' })}</span>
                    <span className="fe-date-chip__day">{today.getDate()}</span>
                </span>
                <div className="fe-event-page__when-text">
                    <span className="fe-event-page__when-date">{__('Event date', 'fluent-event')}</span>
                    <span>{__('Start – end time', 'fluent-event')}</span>
                </div>
            </div>
            <span className="fe-event-page__status">{__('Upcoming', 'fluent-event')}</span>
            <div className="fe-event-page__rsvp">
                <span className="fe-event-page__button">{__('Register', 'fluent-event')}</span>
                <span className="fe-event-page__count">{__('12 going', 'fluent-event')}</span>
            </div>
        </aside>
    ),
};

const PARTS = {
    cover: [__('Event Cover', 'fluent-event'), __('The open event’s cover image.', 'fluent-event'), 'format-image'],
    title: [__('Event Title', 'fluent-event'), __('The open event’s title.', 'fluent-event'), 'heading'],
    meta: [__('Event Meta', 'fluent-event'), __('The open event’s date, time and place.', 'fluent-event'), 'clock'],
    calendar: [__('Event Calendar', 'fluent-event'), __('A link to the open event’s calendar.', 'fluent-event'), 'tag'],
    description: [__('Event Description', 'fluent-event'), __('The open event’s description.', 'fluent-event'), 'text'],
    registration: [__('Event Registration', 'fluent-event'), __('When and where, and the Register button.', 'fluent-event'), 'tickets-alt'],
};

// The Single Event template's pieces: each shows its part of the event whose page is open.
export default function registerEventParts() {
    Object.keys(PARTS).forEach(part => {
        const [title, description, icon] = PARTS[part];
        const Demo = demos[part];

        registerBlockType(`fluent-event/event-${part}`, {
            apiVersion: 2,
            title,
            description,
            icon,
            category: 'widgets',
            keywords: [__('event', 'fluent-event'), 'fluent'],
            supports: { html: false },
            edit: function Edit() {
                return <div {...useBlockProps({ className: 'fe-portal fe-block-demo' })}><Demo /></div>;
            },
            save: () => null,
        });
    });
}
