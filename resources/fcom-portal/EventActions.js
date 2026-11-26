import { t, portalVars, eventsApi, errorMessage, eventDay, eventMonth, eventStart, googleCalendarLink, isSeated, saveIcs } from './utils';
import { APPLE, CHECK, CHEVRON, CLOCK, CROSS, GOOGLE, TICKET, WARNING } from './icons';

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

            return ['not_going', 'waitlisted'].includes(this.event.my_status) ? this.event.my_status : '';
        },
        // Going on a full event with a waitlist joins the waitlist instead.
        joinsWaitlist() {
            return !!this.event.is_full && !!this.event.waitlist && !['going', 'waitlisted'].includes(this.status);
        },
        // A guest already going keeps their RSVP on a full event, to give up the seat.
        isFullForMe() {
            return !!this.event.is_full && !this.event.waitlist && !['going', 'waitlisted'].includes(this.status);
        },
        registerLabel() {
            return this.joinsWaitlist ? t('Join waitlist') : t('Register');
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

                if (done.status === 'waitlisted') {
                    this.showWaitlisted();
                } else {
                    this.showRegistered(done.event);
                }
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

                // An admin's cancel or check-in stands: the member is told why, and nothing changes.
                if (!reply.status) {
                    this.showRefusal(reply.refusal);
                    return;
                }

                const response = this.withStatus(reply);
                this.$emit('done', response);
                // On its own: the feed card's first RSVP otherwise downloads the message box and icons too, for a toast.
                const { ElNotification } = await import('element-plus/es/components/notification/index.mjs');
                ElNotification({ type: 'success', message: response.message });
            } catch (error) {
                this.$emit('error', errorMessage(error));
            } finally {
                this.busy = false;
            }
        },
        // The reply carries the event's seats as they stand now, so the count, spots left and full state move together.
        withStatus(response) {
            return {
                ...response,
                event: {
                    ...this.event,
                    my_status: response.status,
                    online_meeting_url: response.online_meeting_url,
                    guest_count: response.guest_count,
                    spots_left: response.spots_left,
                    is_full: response.is_full,
                },
            };
        },
        async notGoing() {
            this.close();
            const { ElMessageBox } = await import('./element');

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
        async leaveWaitlist() {
            this.close();
            const { ElMessageBox } = await import('./element');

            try {
                await ElMessageBox.confirm(
                    t('You will lose your place on the waitlist for %s.', this.event.title),
                    t('Leave the waitlist?'),
                    { confirmButtonText: t('Leave waitlist'), cancelButtonText: t('Stay on it'), confirmButtonClass: 'el-button--danger' },
                );
            } catch (dismissed) {
                return;
            }

            this.choose('not_going');
        },
        // refusal: 'already_registered', 'waitlisted', or 'cancelled' / 'checked_in' when an admin set the registration's status.
        async showRefusal(refusal) {
            const [{ h }, { ElMessageBox }] = await Promise.all([import('vue'), import('./element')]);
            const [title, text] = {
                cancelled: [t('Your registration was cancelled'), t('Contact the host if you want to join again.')],
                waitlisted: [t("You're already on the waitlist"), t('The host will let you know if a spot opens up.')],
                checked_in: [t('You are already checked in'), t('The host has recorded your attendance. Contact them if something changed.')],
            }[refusal] || [t('This email is already registered'), t('Check your inbox for the confirmation email, or register with a different email.')];

            ElMessageBox.alert(h('div', { class: 'fe-registered' }, [
                h('div', { class: 'fe-registered__head' }, [
                    h('span', { class: 'fe-registered__check fe-registered__check--warning', innerHTML: WARNING }),
                    h('strong', { class: 'fe-registered__title' }, title),
                    h('p', { class: 'fe-registered__text' }, text),
                ]),
            ]), '', {
                customClass: 'fe-registered-box',
                confirmButtonText: t('OK'),
                confirmButtonClass: 'fcom_primary_button',
                closeOnPressEscape: true,
            }).catch(() => {})
                .finally(() => this.$refs.trigger && this.$refs.trigger.focus());
        },
        // Joining holds no seat, so no calendar links: just where the guest stands.
        async showWaitlisted() {
            const [{ h }, { ElMessageBox }] = await Promise.all([import('vue'), import('./element')]);

            ElMessageBox.alert(h('div', { class: 'fe-registered' }, [
                h('div', { class: 'fe-registered__head' }, [
                    h('span', { class: 'fe-registered__check', innerHTML: CLOCK }),
                    h('strong', { class: 'fe-registered__title' }, t("You're on the waitlist")),
                    h('p', { class: 'fe-registered__text' }, t('The host will let you know if a spot opens up.')),
                ]),
            ]), '', {
                customClass: 'fe-registered-box',
                confirmButtonText: t('Done'),
                confirmButtonClass: 'fcom_primary_button',
                closeOnPressEscape: true,
            }).catch(() => {})
                .finally(() => this.$refs.trigger && this.$refs.trigger.focus());
        },
        // Registering is a form, so it ends in a dialog: what it was for, and its calendar links.
        async showRegistered(event) {
            const [{ h }, { ElMessageBox, Clock, MapLocation, VideoCamera }] = await Promise.all([import('vue'), import('./element')]);
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

            <!-- On an open event a member leaves the waitlist from its menu; a visitor isn't recognised again after a reload.
                 An RSVP's waitlist place is an answer in its menu below. -->
            <div v-else-if="status === 'waitlisted' && event.entry_type === 'open' && isLoggedIn" class="fe-rsvp">
                <button
                    ref="trigger"
                    type="button"
                    class="fe-rsvp__trigger"
                    aria-haspopup="menu"
                    :aria-expanded="open ? 'true' : 'false'"
                    :disabled="busy"
                    @click.stop="toggle"
                >
                    <span class="fe-rsvp__icon fe-rsvp__icon--waitlisted" aria-hidden="true">${CLOCK}</span>
                    <span class="fe-rsvp__label">{{ t('On the waitlist') }}</span>
                    <span class="fe-rsvp__chevron" :class="{ 'fe-rsvp__chevron--open': open }" aria-hidden="true">${CHEVRON}</span>
                </button>

                <div v-if="open" class="el-popover el-popper fcom_menu_below_nav fe-rsvp__pop">
                    <ul class="actions_items" role="menu">
                        <li role="none">
                            <button type="button" role="menuitem" class="fe-rsvp__option fe-rsvp__option--danger" @click.stop="leaveWaitlist">{{ t('Leave waitlist') }}</button>
                        </li>
                    </ul>
                </div>
            </div>
            <span v-else-if="status === 'waitlisted' && event.entry_type === 'open'" class="fe-event-actions__badge">
                <span class="fe-event-actions__badge-icon" aria-hidden="true">${CLOCK}</span>
                {{ t('On the waitlist') }}
            </span>

            <span v-else-if="isFullForMe" class="fe-event-actions__state">
                <span class="fe-event-actions__state-icon" aria-hidden="true">${TICKET}</span>
                {{ t('Event full') }}
            </span>

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
                    >{{ registerLabel }}</button>

                    <form
                        v-if="open"
                        class="el-popover el-popper fcom_menu_below_nav fe-guest-register__pop"
                        role="dialog"
                        :aria-label="registerLabel"
                        @submit.prevent="register"
                    >
                        <label class="fe-guest-register__label" :for="'fe-guest-name-' + event.id">{{ t('Name') }}</label>
                        <input ref="name" :id="'fe-guest-name-' + event.id" v-model="guest.name" class="fe-guest-register__input" type="text" autocomplete="name" required>

                        <label class="fe-guest-register__label" :for="'fe-guest-email-' + event.id">{{ t('Email') }}</label>
                        <input :id="'fe-guest-email-' + event.id" v-model="guest.email" class="fe-guest-register__input" type="email" autocomplete="email" required :disabled="isLoggedIn">

                        <input v-if="!isLoggedIn" v-model="guest.website" class="fe-guest-register__trap" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">

                        <p v-if="formError" class="fe-guest-register__error" role="alert">{{ formError }}</p>

                        <button type="submit" class="el-button fcom_primary_button" :disabled="busy">{{ registerLabel }}</button>
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
                    <span v-else-if="status === 'waitlisted'" class="fe-rsvp__icon fe-rsvp__icon--waitlisted" aria-hidden="true">${CLOCK}</span>
                    <span v-else-if="status === 'not_going'" class="fe-rsvp__icon fe-rsvp__icon--not-going" aria-hidden="true">${CROSS}</span>
                    <span class="fe-rsvp__label">{{ { going: t('Going'), waitlisted: t('Waitlisted'), not_going: t('Not going') }[status] || t('Interested?') }}</span>
                    <span class="fe-rsvp__chevron" :class="{ 'fe-rsvp__chevron--open': open }" aria-hidden="true">${CHEVRON}</span>
                </button>

                <div v-if="open" class="el-popover el-popper fcom_menu_below_nav fe-rsvp__pop">
                    <ul class="actions_items" role="menu">
                        <li role="none">
                            <!-- On a full event with a waitlist, Going joins it; a waitlisted guest's place is checked here, and only an admin moves them to going. -->
                            <button v-if="status === 'waitlisted' || joinsWaitlist" type="button" role="menuitemradio" class="fe-rsvp__option" :aria-checked="status === 'waitlisted' ? 'true' : 'false'" @click.stop="choose(status === 'waitlisted' ? 'waitlisted' : 'going')">
                                <span class="fe-rsvp__icon fe-rsvp__icon--waitlisted" aria-hidden="true">${CLOCK}</span>
                                {{ status === 'waitlisted' ? t('Waitlisted') : t('Join waitlist') }}
                            </button>
                            <button v-else type="button" role="menuitemradio" class="fe-rsvp__option" :aria-checked="status === 'going' ? 'true' : 'false'" @click.stop="choose('going')">
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
