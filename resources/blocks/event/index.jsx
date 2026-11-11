const { __ } = wp.i18n;
const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, SelectControl } = wp.components;

function Edit({ attributes, setAttributes }) {
    const { events = [] } = window.fluentEventBlocks || {};
    const event = events.find(item => item.slug === attributes.event);

    return (
        <div {...useBlockProps({ className: 'fe-block-preview' })}>
            <InspectorControls>
                <PanelBody title={__('Event', 'fluent-event')}>
                    <SelectControl
                        label={__('Event', 'fluent-event')}
                        help={__('Leave it on the open event in the Single Event template; pick one to show it on any page.', 'fluent-event')}
                        value={attributes.event}
                        options={[
                            { label: __('The open event', 'fluent-event'), value: '' },
                            ...events.map(item => ({ label: `${item.title} (${item.date})`, value: item.slug })),
                        ]}
                        onChange={value => setAttributes({ event: value })}
                    />
                </PanelBody>
            </InspectorControls>

            <p className="fe-block-preview__title">{event ? event.title : __('Event details', 'fluent-event')}</p>
            <p className="fe-block-preview__meta">
                {event ? event.date : __('The event whose page is open', 'fluent-event')}
            </p>

            <div className="fe-block-preview__event" aria-hidden="true">
                <span className="fe-block-preview__item fe-block-preview__item--cover" />
                <span className="fe-block-preview__item fe-block-preview__item--side" />
            </div>
        </div>
    );
}

export default function registerEventBlock() {
    registerBlockType('fluent-event/event', {
        apiVersion: 2,
        title: __('Fluent Event', 'fluent-event'),
        description: __('One event with its details and registration.', 'fluent-event'),
        category: 'widgets',
        icon: 'calendar',
        keywords: [__('event', 'fluent-event'), __('registration', 'fluent-event'), 'fluent'],
        supports: { align: ['wide', 'full'], html: false },
        attributes: {
            event: { type: 'string', default: '' },
        },
        edit: Edit,
        save: () => null,
    });
}
