import { t, portalVars, eventsApi, errorMessage, eventDay, eventMonth, eventStart, googleCalendarLink, isSeated, saveIcs } from './utils';
import { APPLE, CHECK, CHEVRON, CROSS, GOOGLE, WARNING } from './icons';

/*
 * The RSVP dropdown, or an open event's Register form (no undo): a member's arrives filled in,
 * email locked to the account. A none event has neither.
 * The feed card runs on fcom's Vue and the Events list on ours, so this is a
 * string template both can compile, and its popover wears fcom's classes.
 * Our Vue and Element Plus load only when a dialog or toast opens: the feed card
 * imports this file, and every portal page would otherwise download them.
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
        // Back here after login, as fcom's getCurrentAuthUrl does; also read at click, as the URL changes without a reload.
        loginUrl() {
            const url = new URL((window.fluentComAdmin || {}).auth_url || '', window.location.href);
            url.searchParams.set('redirect_to', window.location.href);

            return url.href;
        },
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

                // A refused registration comes back without a status: nothing changed, so it is warned, not confirmed.
                if (!response.status) {
                    this.showRefusal(response.refusal);
                    return;
                }

                const done = this.withStatus(response);
                this.$emit('done', done);
                this.showRegistered(done.event);
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
                const reply = await eventsApi.post(`community/events/${this.event.id}/register`, { status });

                // An admin's cancel stands: the member is told why, and nothing changes.
                if (!reply.status) {
                    this.showRefusal(reply.refusal);
                    return;
                }

                const response = this.withStatus(reply);
                this.$emit('done', response);
                const { ElNotification } = await import('element-plus');
                ElNotification({ type: 'success', message: response.message });
            } catch (error) {
                this.$emit('error', errorMessage(error));
            } finally {
                this.busy = false;
            }
        },
        // The reply carries only the new status and link; the count moves by this answer alone, and a reload brings everyone else's.
        withStatus(response) {
            const wasSeated = isSeated(this.event);
            const isNowSeated = isSeated({ my_status: response.status });
            const count = this.event.guest_count;

            return {
                ...response,
                event: {
                    ...this.event,
                    my_status: response.status,
                    online_meeting_url: response.online_meeting_url,
                    guest_count: count === null || count === undefined || wasSeated === isNowSeated ? count : count + (isNowSeated ? 1 : -1),
                },
            };
        },
        async notGoing() {
            this.close();
            const { ElMessageBox } = await import('element-plus');

            try {
                await ElMessageBox.confirm(
                    t('You will lose your spot for %s. You can register again later.', this.event.title),
                    t('Not going anymore?'),
                    { confirmButtonText: t('Not going'), cancelButtonText: t('Keep it'), confirmButtonClass: 'el-button--danger' },
                );
            } catch (dismissed) {
                return;
            }

            this.choose('not_going');
        },
        // refusal: 'already_registered', or 'cancelled' when an admin cancelled the registration.
        async showRefusal(refusal) {
            const [{ h }, { ElMessageBox }] = await Promise.all([import('vue'), import('element-plus')]);
            const cancelled = refusal === 'cancelled';

            ElMessageBox.alert(h('div', { class: 'fe-registered' }, [
                h('div', { class: 'fe-registered__head' }, [
                    h('span', { class: 'fe-registered__check fe-registered__check--warning', innerHTML: WARNING }),
                    h('strong', { class: 'fe-registered__title' }, cancelled ? t('Your registration was cancelled by the host') : t('This email is already registered')),
                    h('p', { class: 'fe-registered__text' }, cancelled
                        ? t('Contact the host if you want to join again.')
                        : t('Check your inbox for the confirmation email, or register with a different email.')),
                ]),
            ]), '', {
                customClass: 'fe-registered-box',
                confirmButtonText: t('OK'),
                confirmButtonClass: 'fcom_primary_button',
                closeOnPressEscape: true,
            }).catch(() => {})
                .finally(() => this.$refs.trigger && this.$refs.trigger.focus());
        },
        // Registering is a form, so it ends in a dialog: what it was for, and its calendar links.
        async showRegistered(event) {
            const [{ h }, { ElMessageBox }, { Clock, MapLocation, VideoCamera }] = await Promise.all([
                import('vue'),
                import('element-plus'),
                import('@element-plus/icons-vue'),
            ]);
            const online = event.venue_type === 'online';
            const location = online
                ? (/^https?:\/\//i.test(event.online_meeting_url) ? event.online_meeting_url : '')
                : [event.venue, event.address].filter(Boolean).join(', ');
            // The SVGs are icons.js constants, never user data.
            const svg = (markup) => h('span', { class: 'fe-registered__icon', innerHTML: markup });

            ElMessageBox.alert(h('div', { class: 'fe-registered' }, [
                h('div', { class: 'fe-registered__head' }, [
                    h('span', { class: 'fe-registered__check', innerHTML: CHECK }),
                    h('strong', { class: 'fe-registered__title' }, t('You\'re registered')),
                ]),
                h('div', { class: 'fe-registered__event' }, [
                    event.cover_url
                        ? h('img', { class: 'fe-registered__cover', src: event.cover_url, alt: '' })
                        : h('span', { class: 'fe-registered__chip', 'aria-hidden': 'true' }, [
                            h('span', { class: 'fe-registered__chip-month' }, eventMonth(event)),
                            h('span', { class: 'fe-registered__chip-day' }, eventDay(event)),
                        ]),
                    h('div', { class: 'fe-registered__details' }, [
                        h('span', { class: 'fe-registered__event-title' }, event.title),
                        h('span', { class: 'fe-registered__meta' }, [h(Clock, { class: 'fe-registered__meta-icon' }), eventStart(event)]),
                        h('span', { class: 'fe-registered__meta' }, [
                            h(online ? VideoCamera : MapLocation, { class: 'fe-registered__meta-icon' }),
                            online ? t('Online') : (event.venue || t('In Person')),
                        ]),
                    ]),
                ]),
                h('span', { class: 'fe-registered__label' }, t('Add to calendar')),
                h('div', { class: 'fe-registered__calendar' }, [
                    h('a', { class: 'fe-registered__calendar-link', href: googleCalendarLink(event, '', location), target: '_blank', rel: 'noopener noreferrer' }, [svg(GOOGLE), t('Google Calendar')]),
                    h('button', { type: 'button', class: 'fe-registered__calendar-link', onClick: () => saveIcs(event, '', location) }, [svg(APPLE), t('Apple / Outlook')]),
                ]),
            ]), '', {
                customClass: 'fe-registered-box',
                confirmButtonText: t('Done'),
                confirmButtonClass: 'fcom_primary_button',
                // alert() defaults to ignoring Esc; a dialog must close on it.
                closeOnPressEscape: true,
            }).catch(() => {})
                // Back to the "Registered" menu that replaced the Register button, not lost on <body>.
                .finally(() => this.$refs.trigger && this.$refs.trigger.focus());
        },
    },
    template: `
        <div class="fe-event-actions">
            <span v-if="event.is_past" class="fe-event-actions__badge">{{ t('Ended') }}</span>

            <template v-else-if="event.entry_type === 'open'">
                <!-- A visitor isn't recognised again after a reload, so only a member's badge opens a menu to cancel. -->
                <div v-if="status === 'going' && isLoggedIn" class="fe-rsvp">
                    <button
                        ref="trigger"
                        type="button"
                        class="fe-rsvp__trigger"
                        aria-haspopup="menu"
                        :aria-expanded="open ? 'true' : 'false'"
                        :disabled="busy"
                        @click.stop="toggle"
                    >
                        <span class="fe-rsvp__icon fe-rsvp__icon--going" aria-hidden="true">${CHECK}</span>
                        <span class="fe-rsvp__label">{{ t('Registered') }}</span>
                        <span class="fe-rsvp__chevron" :class="{ 'fe-rsvp__chevron--open': open }" aria-hidden="true">${CHEVRON}</span>
                    </button>

                    <div v-if="open" class="el-popover el-popper fcom_menu_below_nav fe-rsvp__pop">
                        <ul class="actions_items" role="menu">
                            <li role="none">
                                <button type="button" role="menuitem" class="fe-rsvp__option fe-rsvp__option--danger" @click.stop="notGoing">{{ t('Not going') }}</button>
                            </li>
                        </ul>
                    </div>
                </div>
                <span v-else-if="status === 'going'" class="fe-event-actions__badge">
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

            <a v-else-if="!isLoggedIn" class="el-button fcom_primary_button" :href="loginUrl()" @click="$event.currentTarget.href = loginUrl()">{{ t('Log in to RSVP') }}</a>

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
