import { t, portalVars, eventsApi, errorMessage, isSeated } from './utils';

/*
 * Register / RSVP controls; an open registration is cancelled from the ⋮ menu.
 * The feed card runs on fcom's Vue and the Events list on ours, so this is a
 * string template both can compile.
 */
export default {
    name: 'FluentEventActions',
    props: {
        event: { type: Object, required: true },
    },
    emits: ['done', 'error'],
    data() {
        return {
            busy: false,
        };
    },
    computed: {
        isLoggedIn() {
            return !!portalVars().is_logged_in;
        },
        isSeated() {
            return isSeated(this.event);
        },
        loginUrl() {
            return (window.fluentComAdmin && window.fluentComAdmin.auth_url) || '';
        },
    },
    methods: {
        t,
        async send(route, payload) {
            this.busy = true;
            this.$emit('error', '');

            try {
                this.$emit('done', await eventsApi.post(`community/events/${this.event.id}/${route}`, payload));
            } catch (error) {
                this.$emit('error', errorMessage(error));
            } finally {
                this.busy = false;
            }
        },
        rsvp(status) {
            this.send('register', { status });
        },
        // Open registration always asks for a name and email, prefilled for members.
        async register() {
            this.$emit('error', '');

            const { openRegisterDialog } = await import('./app/fcom-portal-app.js');
            const response = await openRegisterDialog({ eventId: this.event.id, eventTitle: this.event.title });

            if (response) {
                this.$emit('done', response);
            }
        },
    },
    template: `
        <div class="fe-event-actions">
            <span v-if="event.is_past" class="fe-event-actions__badge">{{ t('Ended') }}</span>

            <template v-else-if="event.entry_type === 'rsvp'">
                <a v-if="!isLoggedIn" class="el-button fcom_primary_button" :href="loginUrl">{{ t('Log in to RSVP') }}</a>
                <div v-else class="fe-event-actions__rsvp" role="group" :aria-label="t('RSVP')">
                    <button type="button" class="el-button" :class="isSeated ? 'fcom_primary_button' : 'fcom_secondary_button'" :aria-pressed="isSeated" :disabled="busy || (!isSeated && event.is_full)" @click="rsvp('going')">{{ t('Going') }}</button>
                    <button type="button" class="el-button" :class="event.my_status === 'not_going' ? 'el-button--info' : 'fcom_secondary_button'" :aria-pressed="event.my_status === 'not_going'" :disabled="busy" @click="rsvp('not_going')">{{ t('Not going') }}</button>
                </div>
            </template>

            <button v-else-if="event.entry_type === 'open' && !isSeated" type="button" class="el-button fcom_primary_button" :disabled="busy || event.is_full" @click="register">{{ t('Register') }}</button>
            <span v-else-if="event.entry_type === 'open'" class="fe-event-status">{{ t('Registered') }}</span>

            <span v-if="event.is_full && !isSeated && !event.is_past" class="fe-event-actions__badge">{{ t('Event is full') }}</span>
        </div>
    `,
};
