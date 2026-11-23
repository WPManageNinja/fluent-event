import { t, eventsApi, eventStart, eventMonth, eventDay } from './utils';

/*
 * The space's next events, at the top of fcom's space sidebar (its fluent_com_space_sidebar_widgets filter).
 * A string template on fcom's Vue, like the event card; it takes fcom's sidebar widget markup so it sits in line.
 */
export default {
    name: 'FluentEventSpaceEventsWidget',
    props: {
        space_profile: { type: Object, required: true },
    },
    data() {
        return {
            events: [],
            loading: true,
            error: '',
            requestId: 0,
        };
    },
    watch: {
        'space_profile.slug'() {
            this.fetchEvents();
        },
    },
    mounted() {
        this.fetchEvents();
    },
    methods: {
        t,
        startDate: eventStart,
        month: eventMonth,
        day: eventDay,
        async fetchEvents() {
            const requestId = ++this.requestId;
            this.loading = true;
            this.error = '';

            try {
                const response = await eventsApi.get('community/events', { space: this.space_profile.slug, scope: 'upcoming', per_page: 3 });

                // The member moved to another space; its own request owns the list now.
                if (requestId === this.requestId) {
                    this.events = response.events || [];
                }
            } catch (error) {
                if (requestId === this.requestId) {
                    this.events = [];
                    this.error = t('Something went wrong. Please try again.');
                }
            } finally {
                if (requestId === this.requestId) {
                    this.loading = false;
                }
            }
        },
    },
    template: `
        <div role="region" :aria-label="t('Upcoming Events')" class="app_side_widget fe-space-events">
            <div class="widget_header fe-space-events__header">
                <h3>{{ t('Upcoming Events') }}</h3>
                <router-link
                    v-if="events.length"
                    class="fe-space-events__all"
                    :to="{ name: 'fe_space_events', params: { space: space_profile.slug } }"
                >{{ t('View all') }}</router-link>
            </div>
            <div class="widget_body">
                <div v-if="loading" class="fe-space-events__skeleton" role="status" :aria-label="t('Loading events')">
                    <span class="fe-space-events__skeleton-line"></span>
                    <span class="fe-space-events__skeleton-line fe-space-events__skeleton-line--short"></span>
                </div>
                <p v-else-if="error" class="fe-space-events__note" role="alert">{{ error }}</p>
                <p v-else-if="!events.length" class="fe-space-events__note">{{ t('No upcoming events') }}</p>
                <ul v-else class="fe-space-events__list">
                    <li v-for="event in events" :key="event.id" class="fe-space-events__item">
                        <div class="fe-date-chip fe-space-events__chip" aria-hidden="true">
                            <span class="fe-date-chip__month">{{ month(event) }}</span>
                            <span class="fe-date-chip__day">{{ day(event) }}</span>
                        </div>
                        <div class="fe-space-events__info">
                            <router-link
                                class="fe-space-events__title"
                                :to="{ name: 'fe_event_view', params: { space: event.space_slug, event_slug: event.slug } }"
                            >{{ event.title }}</router-link>
                            <span class="fe-space-events__date">{{ startDate(event) }}</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    `,
};
