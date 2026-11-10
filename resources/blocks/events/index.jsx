const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, SelectControl, ToggleControl } = wp.components;

const VIEW_LABELS = {
    cards: __('Cards', 'fluent-event'),
    timeline: __('Timeline', 'fluent-event'),
    calendar: __('Calendar', 'fluent-event'),
};

function Edit({ attributes, setAttributes }) {
    const { calendars = [] } = window.fluentEventBlocks || {};
    const showCalendars = attributes.show_calendars !== 'no';
    const calendar = calendars.find(item => item.slug === attributes.calendar);
    const view = attributes.view || 'timeline';

    return (
        <div {...useBlockProps({ className: 'fe-block-preview' })}>
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

            <p className="fe-block-preview__title">{__('Events', 'fluent-event')}</p>
            <p className="fe-block-preview__meta">
                {[
                    calendar ? calendar.title : __('All calendars', 'fluent-event'),
                    attributes.view ? VIEW_LABELS[view] : __('View from the settings', 'fluent-event'),
                ].join(' · ')}
            </p>

            {showCalendars && (
                <div className="fe-block-preview__pills" aria-hidden="true">
                    <span className="fe-block-preview__pill fe-block-preview__pill--active" />
                    <span className="fe-block-preview__pill" />
                    <span className="fe-block-preview__pill" />
                </div>
            )}

            <div className={`fe-block-preview__list fe-block-preview__list--${view}`} aria-hidden="true">
                {[0, 1, 2].map(index => <span key={index} className="fe-block-preview__item" />)}
            </div>
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
