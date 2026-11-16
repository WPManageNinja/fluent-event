import { demos } from '../event-parts/index.jsx';

import { __ } from '../i18n.js';
const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, SelectControl } = wp.components;
const { useSelect } = wp.data;

function Edit({ attributes, setAttributes }) {
    const { events = [] } = window.fluentEventBlocks || {};
    // In the Single Event template it always shows the event whose page is open, so there is nothing to pick.
    const inEventTemplate = useSelect(select => {
        const editor = select('core/editor');

        return String((editor && editor.getCurrentPostId()) || '').endsWith('//single-fe_event');
    }, []);

    return (
        <div {...useBlockProps({ className: 'fe-portal fe-block-demo' })}>
            {!inEventTemplate && (
                <InspectorControls>
                    <PanelBody title={__('Event', 'fluent-event')}>
                        <SelectControl
                            label={__('Event', 'fluent-event')}
                            value={attributes.event}
                            options={[
                                { label: __('Select an event', 'fluent-event'), value: '' },
                                ...events.map(item => ({ label: `${item.title} (${item.date})`, value: item.slug })),
                            ]}
                            onChange={value => setAttributes({ event: value })}
                        />
                    </PanelBody>
                </InspectorControls>
            )}

            {/* Always a sample, never a real event: the page can be laid out before any exists. */}
            <div className="fe-event-page">
                {demos.cover()}

                <div className="fe-event-page__grid">
                    <div className="fe-event-page__main">
                        <div className="fe-event-page__intro">
                            {demos.title()}
                            {demos.meta()}
                            {demos.calendar()}
                        </div>

                        {demos.description()}
                    </div>

                    {demos.registration()}
                </div>
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
