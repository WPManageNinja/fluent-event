import { t, portalVars, eventsApi, errorMessage, isSeated } from './utils';

/*
 * RSVP controls; an open event is view-only and has none.
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
        async rsvp(status) {
            this.busy = true;
            this.$emit('error', '');

            try {
                this.$emit('done', await eventsApi.post(`community/events/${this.event.id}/register`, { status }));
            } catch (error) {
                this.$emit('error', errorMessage(error));
            } finally {
                this.busy = false;
            }
        },
    },
    template: `
        <div class="fe-event-actions">
            <span v-if="event.is_past" class="fe-event-actions__badge">{{ t('Ended') }}</span>

            <template v-else-if="event.entry_type === 'rsvp'">
                <a v-if="!isLoggedIn" class="el-button fcom_primary_button" :href="loginUrl">{{ t('Log in to RSVP') }}</a>
                <div v-else class="fe-event-actions__rsvp" role="group" :aria-label="t('RSVP')">
                    <button type="button" class="el-button" :class="isSeated ? 'fcom_primary_button' : 'fcom_secondary_button'" :aria-pressed="isSeated" :disabled="busy" @click="rsvp('going')">{{ t('Going') }}</button>
                    <button type="button" class="el-button" :class="event.my_status === 'not_going' ? 'el-button--info' : 'fcom_secondary_button'" :aria-pressed="event.my_status === 'not_going'" :disabled="busy" @click="rsvp('not_going')">{{ t('Not going') }}</button>
                </div>
            </template>
        </div>
    `,
};
