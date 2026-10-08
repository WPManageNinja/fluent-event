import { createApp } from 'vue';
import EventsPage from './EventsPage.vue';
import CreateEventPage from './CreateEventPage.vue';
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
