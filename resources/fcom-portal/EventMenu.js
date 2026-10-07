import { t, eventsApi, errorMessage, canCancelRegistration } from './utils';

// The "⋮" menu on an event. fcom exposes no popover to add-ons, so this one is ours.
export default {
    name: 'FluentEventMenu',
    props: {
        event: { type: Object, required: true },
    },
    emits: ['done', 'error'],
    data() {
        return {
            open: false,
            busy: false,
        };
    },
    computed: {
        canCancel() {
            return canCancelRegistration(this.event);
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

            if (this.open) {
                this.$nextTick(() => {
                    const first = this.$el.querySelector('[role="menuitem"]');
                    first && first.focus();
                });
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
                this.$refs.trigger.focus();
            }
        },
        async cancel() {
            this.close();
            // The item is gone with the menu, so the box hands focus back to the trigger.
            this.$refs.trigger.focus();

            const { confirmAction } = await import('./app/fcom-portal-app.js');
            const confirmed = await confirmAction(
                t('Are you sure you want to cancel your registration?'),
                t('Cancel registration'),
                t('Keep registration')
            );

            if (!confirmed) {
                return;
            }

            this.busy = true;
            this.$emit('error', '');

            try {
                this.$emit('done', await eventsApi.post(`community/events/${this.event.id}/cancel`, {}));
            } catch (error) {
                this.$emit('error', errorMessage(error));
            } finally {
                this.busy = false;
            }
        },
    },
    // fcom's post-menu classes (el-button.fcom_dot_menu, el-popover, actions_items), so fcom's CSS paints it identically.
    template: `
        <div v-if="event.edit_url || canCancel" class="fe-event-menu">
            <button
                ref="trigger"
                type="button"
                class="el-button el-button--small is-text fcom_dot_menu"
                aria-haspopup="menu"
                :aria-expanded="open ? 'true' : 'false'"
                :aria-label="t('More actions')"
                :disabled="busy"
                @click.stop="toggle"
            >
                <span>
                    <i class="el-icon" aria-hidden="true">
                        <svg viewBox="0 0 1024 1024">
                            <path fill="currentColor" d="M176 416a112 112 0 1 1 0 224 112 112 0 0 1 0-224m336 0a112 112 0 1 1 0 224 112 112 0 0 1 0-224m336 0a112 112 0 1 1 0 224 112 112 0 0 1 0-224"/>
                        </svg>
                    </i>
                </span>
            </button>

            <div v-if="open" class="el-popover el-popper fcom_menu_below_nav fe-event-menu__pop">
                <ul class="actions_items" role="menu">
                    <li v-if="event.edit_url" role="none">
                        <!-- .stop: fcom's document click handler would route this admin URL inside the portal on a root-mounted portal. -->
                        <a role="menuitem" class="fe-event-menu__item" :href="event.edit_url" @click.stop>{{ t('Edit event') }}</a>
                    </li>
                    <li v-if="canCancel" role="none">
                        <button type="button" role="menuitem" class="fe-event-menu__item" @click.stop="cancel">{{ t('Cancel registration') }}</button>
                    </li>
                </ul>
            </div>
        </div>
    `,
};
