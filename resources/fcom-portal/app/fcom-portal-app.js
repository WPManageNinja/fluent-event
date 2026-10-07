import { createApp } from 'vue';
import EventsPage from './EventsPage.vue';
import CreateEventPage from './CreateEventPage.vue';
import RegisterDialog from './RegisterDialog.vue';
import { t } from '../utils';

const pages = {
    events: EventsPage,
    space_events: EventsPage,
    create_event: CreateEventPage,
};

// A separate app with its own Vue and Element Plus; fcom exposes neither to add-ons.
export function mountPage(el, props) {
    const app = createApp(pages[props.page], props);
    app.config.globalProperties.$t = t;
    app.mount(el);

    return () => app.unmount();
}

// Resolves with the register response, or null when the dialog is dismissed.
export function openRegisterDialog(props) {
    return new Promise((resolve) => {
        const el = document.createElement('div');
        let response = null;

        const app = createApp(RegisterDialog, {
            ...props,
            onDone: (result) => {
                response = result;
            },
            onClosed: () => {
                app.unmount();
                el.remove();
                resolve(response);
            },
        });

        app.config.globalProperties.$t = t;
        document.body.appendChild(el);
        app.mount(el);
    });
}
