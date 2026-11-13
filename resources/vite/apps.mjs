// The apps `npm run build` builds one per run (resources/vite/build.mjs), as FluentCommunity does.
// A JS app builds with cssCodeSplit off, so all of its CSS (lazy routes and Element Plus included)
// lands in one stylesheet that PHP enqueues and the RTL step can mirror: no CSS is injected at
// runtime. The SCSS entries build together in their own run. The dev server serves every input.
// The first input of an app is the entry its stylesheet is attached to.
export const APPS = {
    admin: ['resources/admin/app.js', 'resources/admin/start.js'],
    // FluentCommunity portal: the route/card loader.
    'fcom-portal': ['resources/fcom-portal/fcom-portal.js'],
    // The public pages' list and event page, and the event page's registration.
    portal: ['resources/portal/portal.js'],
    // The [fluent_events] and [fluent_event] blocks' editor side.
    blocks: ['resources/blocks/blocks.jsx'],
    styles: [
        'resources/scss/admin.scss',
        'resources/fcom-portal/fcom-portal.scss',
        'resources/portal/portal.scss',
        'resources/blocks/blocks.scss'
    ]
};
