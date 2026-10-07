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
    // fcom's post-menu classes (el-button.fcom_dot_menu, el-popover, actions_items), so fcom's CSS paints it identically.
    template: `
        <div class="fe-event-menu">
            <button
                ref="trigger"
                type="button"
                class="el-button el-button--small is-text fcom_dot_menu"
                aria-haspopup="menu"
                :aria-expanded="open ? 'true' : 'false'"
                :aria-label="t('More actions')"
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
                    <li role="none">
                        <!-- .stop: fcom's document click handler would route this admin URL inside the portal on a root-mounted portal. -->
                        <a ref="edit" role="menuitem" class="fe-event-menu__item" :href="editUrl" @click.stop>{{ t('Edit event') }}</a>
                    </li>
                </ul>
            </div>
        </div>
    `,
};
