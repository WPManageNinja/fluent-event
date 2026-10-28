import { t, portalVars, eventsApi, errorMessage, isSeated, siteDateTime } from './utils';
import { CHECK, CHEVRON, CLOCK, CROSS, TICKET } from './icons';

/*
 * The RSVP dropdown; an open event is view-only and has none.
 * The feed card runs on fcom's Vue and the Events list on ours, so this is a
 * string template both can compile, and its popover wears fcom's classes.
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
            open: false,
        };
    },
    computed: {
        isLoggedIn() {
            return !!portalVars().is_logged_in;
        },
        loginUrl() {
            return (window.fluentComAdmin && window.fluentComAdmin.auth_url) || '';
        },
        // A checked-in guest counts as going.
        status() {
            if (isSeated(this.event)) {
                return 'going';
            }

            return ['not_going', 'waitlisted', 'cancelled'].includes(this.event.my_status) ? this.event.my_status : '';
        },
        // The member answers only going / not going; any other status is the host's or the waitlist's to change.
        stateLabel() {
            return { waitlisted: t('Waitlisted'), cancelled: t('Cancelled') }[this.status] || '';
        },
        isFullForMe() {
            return !!this.event.is_full && this.status !== 'going';
        },
        // Outside the registration window only a member already going keeps the RSVP, to give up the seat.
        registrationNote() {
            if (this.status === 'going' || this.event.registration_open) {
                return '';
            }

            return this.event.registration_opens_at
                ? t('Opens %s', siteDateTime(this.event.registration_opens_at))
                : t('Registration closed');
        },
    },
    beforeUnmount() {
        this.listen(false);
    },
    methods: {
        t,
        toggle() {
            this.open = !this.open;
            this.listen(this.open);
        },
        close() {
            this.open = false;
            this.listen(false);
        },
        listen(on) {
            const method = on ? 'addEventListener' : 'removeEventListener';
            document[method]('click', this.onOutsideClick);
            document[method]('keydown', this.onKeydown);
        },
        onOutsideClick(event) {
            if (!this.$el.contains(event.target)) {
                this.close();
            }
        },
        onKeydown(event) {
            if (event.key === 'Escape') {
                this.close();
                this.$refs.trigger && this.$refs.trigger.focus();
            }
        },
        async choose(status) {
            this.close();

            if (status === this.status) {
                return;
            }

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

            <span v-else-if="stateLabel" class="fe-event-actions__state" :class="'fe-event-actions__state--' + status">
                <span v-if="status === 'waitlisted'" class="fe-event-actions__state-icon" aria-hidden="true">${CLOCK}</span>
                <span v-else class="fe-event-actions__state-icon" aria-hidden="true">${CROSS}</span>
                {{ stateLabel }}
            </span>

            <span v-else-if="registrationNote" class="fe-event-actions__state">
                <span v-if="event.registration_opens_at" class="fe-event-actions__state-icon" aria-hidden="true">${CLOCK}</span>
                <span v-else class="fe-event-actions__state-icon" aria-hidden="true">${TICKET}</span>
                {{ registrationNote }}
            </span>

            <span v-else-if="isFullForMe && !event.waitlist" class="fe-event-actions__state">
                <span class="fe-event-actions__state-icon" aria-hidden="true">${TICKET}</span>
                {{ t('Event full') }}
            </span>

            <a v-else-if="!isLoggedIn" class="el-button fcom_primary_button" :href="loginUrl">{{ t('Log in to RSVP') }}</a>

            <!-- The server files it as waitlisted while the event is full. -->
            <button
                v-else-if="isFullForMe"
                type="button"
                class="el-button fcom_primary_button fe-event-actions__join"
                :disabled="busy"
                @click="choose('going')"
            >{{ t('Join waitlist') }}</button>

            <div v-else class="fe-rsvp">
                <button
                    ref="trigger"
                    type="button"
                    class="fe-rsvp__trigger"
                    aria-haspopup="menu"
                    :aria-expanded="open ? 'true' : 'false'"
                    :disabled="busy"
                    @click.stop="toggle"
                >
                    <span v-if="status === 'going'" class="fe-rsvp__icon fe-rsvp__icon--going" aria-hidden="true">${CHECK}</span>
                    <span v-else-if="status === 'not_going'" class="fe-rsvp__icon fe-rsvp__icon--not-going" aria-hidden="true">${CROSS}</span>
                    <span class="fe-rsvp__label">{{ status === 'going' ? t('Going') : status === 'not_going' ? t('Not going') : t('Interested?') }}</span>
                    <span class="fe-rsvp__chevron" :class="{ 'fe-rsvp__chevron--open': open }" aria-hidden="true">${CHEVRON}</span>
                </button>

                <div v-if="open" class="el-popover el-popper fcom_menu_below_nav fe-rsvp__pop">
                    <ul class="actions_items" role="menu">
                        <li role="none">
                            <button type="button" role="menuitemradio" class="fe-rsvp__option" :aria-checked="status === 'going' ? 'true' : 'false'" @click.stop="choose('going')">
                                <span class="fe-rsvp__icon fe-rsvp__icon--going" aria-hidden="true">${CHECK}</span>
                                {{ t('Going') }}
                            </button>
                        </li>
                        <li role="none">
                            <button type="button" role="menuitemradio" class="fe-rsvp__option" :aria-checked="status === 'not_going' ? 'true' : 'false'" @click.stop="choose('not_going')">
                                <span class="fe-rsvp__icon fe-rsvp__icon--not-going" aria-hidden="true">${CROSS}</span>
                                {{ t('Not going') }}
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    `,
};
