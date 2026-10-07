import { t } from './utils';

// The "⋮" menu on an event. fcom exposes no popover to add-ons, so this one is ours.
export default {
    name: 'FluentEventMenu',
    props: {
        editUrl: { type: String, required: true },
    },
    data() {
        return {
            open: false,
        };
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
                this.$nextTick(() => this.$refs.edit && this.$refs.edit.focus());
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
    },
    template: `
        <div class="fe-event-menu">
            <button
                ref="trigger"
                type="button"
                class="fe-event-menu__trigger"
                aria-haspopup="menu"
                :aria-expanded="open ? 'true' : 'false'"
                :aria-label="t('More actions')"
                @click.stop="toggle"
            >
                <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                    <circle cx="8" cy="3" r="1.5" fill="currentColor"/>
                    <circle cx="8" cy="8" r="1.5" fill="currentColor"/>
                    <circle cx="8" cy="13" r="1.5" fill="currentColor"/>
                </svg>
            </button>

            <ul v-if="open" class="fe-event-menu__list" role="menu">
                <li role="none">
                    <!-- .stop: fcom's document click handler would route this admin URL inside the portal on a root-mounted portal. -->
                    <a ref="edit" role="menuitem" class="fe-event-menu__item" :href="editUrl" @click.stop>{{ t('Edit event') }}</a>
                </li>
            </ul>
        </div>
    `,
};
