import { t, portalVars, eventsApi, errorMessage, isSeated } from './utils';
import { CHECK, CHEVRON, CROSS } from './icons';

/*
 * The RSVP dropdown, or an open event's Register form (no undo): a member's arrives filled in,
 * email locked to the account. A none event has neither.
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
        const user = portalVars().user || {};

        return {
            busy: false,
            open: false,
            guest: { name: user.name || '', email: user.email || '', website: '' },
            formError: '',
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

            return this.event.my_status === 'not_going' ? 'not_going' : '';
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

            // The Register form opens with its first field focused.
            if (this.open) {
                this.$nextTick(() => this.$refs.name && this.$refs.name.focus());
            }
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
        async register() {
            this.busy = true;
            this.formError = '';

            try {
                const response = this.isLoggedIn
                    ? await eventsApi.post(`community/events/${this.event.id}/register`, { status: 'going', name: this.guest.name })
                    : await eventsApi.post(`community/events/${this.event.id}/register-guest`, {
                        ...this.guest,
                        nonce: this.event.register_nonce,
                    });
                this.close();
                this.$emit('done', response);
            } catch (error) {
                this.formError = errorMessage(error);
            } finally {
                this.busy = false;
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

            <template v-else-if="event.entry_type === 'open'">
                <span v-if="status === 'going'" class="fe-event-actions__badge">
                    <span class="fe-event-actions__badge-icon" aria-hidden="true">${CHECK}</span>
                    {{ t('Registered') }}
                </span>
                <div v-else class="fe-guest-register">
                    <button
                        ref="trigger"
                        type="button"
                        class="el-button fcom_primary_button"
                        aria-haspopup="dialog"
                        :aria-expanded="open ? 'true' : 'false'"
                        @click.stop="toggle"
                    >{{ t('Register') }}</button>

                    <form
                        v-if="open"
                        class="el-popover el-popper fcom_menu_below_nav fe-guest-register__pop"
                        role="dialog"
                        :aria-label="t('Register')"
                        @submit.prevent="register"
                    >
                        <label class="fe-guest-register__label" :for="'fe-guest-name-' + event.id">{{ t('Name') }}</label>
                        <input ref="name" :id="'fe-guest-name-' + event.id" v-model="guest.name" class="fe-guest-register__input" type="text" autocomplete="name" required>

                        <label class="fe-guest-register__label" :for="'fe-guest-email-' + event.id">{{ t('Email') }}</label>
                        <input :id="'fe-guest-email-' + event.id" v-model="guest.email" class="fe-guest-register__input" type="email" autocomplete="email" required :disabled="isLoggedIn">

                        <input v-if="!isLoggedIn" v-model="guest.website" class="fe-guest-register__trap" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

                        <p v-if="formError" class="fe-guest-register__error" role="alert">{{ formError }}</p>

                        <button type="submit" class="el-button fcom_primary_button" :disabled="busy">{{ t('Register') }}</button>
                    </form>
                </div>
            </template>

            <a v-else-if="!isLoggedIn" class="el-button fcom_primary_button" :href="loginUrl">{{ t('Log in to RSVP') }}</a>

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
