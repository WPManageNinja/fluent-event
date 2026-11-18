import { portalVars, t } from './utils';
import { DOTS } from './icons';

// The "⋮" menu on an event. fcom exposes no popover to add-ons, so this one is ours.
export default {
    name: 'FluentEventMenu',
    props: {
        event: { type: Object, required: true },
        // Our page apps have no $router of their own, so they pass fcom's.
        router: { type: Object, default: null },
    },
    data() {
        return {
            open: false,
            shift: 0,
        };
    },
    computed: {
        editRoute() {
            return { name: 'fe_edit_event', params: { event_slug: this.event.slug } };
        },
        // The event's page in the FluentEvent admin, where its guests are managed.
        adminUrl() {
            const base = portalVars().admin_url;
            return base ? base + '#/events/' + this.event.id : '';
        },
        portalRouter() {
            return this.router || this.$router;
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
                this.shift = 0;
                this.$nextTick(() => {
                    this.keepOnScreen();
                    const first = this.$el.querySelector('[role="menuitem"]');
                    first && first.focus();
                });
            }
        },
        // The menu hangs left from the "⋮"; on a phone a short title puts the "⋮" near the left edge, so slide it back in.
        keepOnScreen() {
            const box = this.$refs.pop.getBoundingClientRect();
            const edge = 8;
            const right = document.documentElement.clientWidth - edge;

            if (box.left < edge) {
                this.shift = edge - box.left;
            } else if (box.right > right) {
                this.shift = right - box.right;
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
        <div v-if="event.edit_url" class="fe-event-menu">
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
                        ${DOTS}
                    </i>
                </span>
            </button>

            <div
                v-if="open"
                ref="pop"
                class="el-popover el-popper fcom_menu_below_nav fe-event-menu__pop"
                :style="shift ? { transform: 'translateX(' + shift + 'px)' } : null"
            >
                <ul class="actions_items" role="menu">
                    <li role="none">
                        <!-- .stop: fcom's document click handler would route the link a second time. -->
                        <a role="menuitem" class="fe-event-menu__item" :href="portalRouter.resolve(editRoute).href" @click.stop.prevent="portalRouter.push(editRoute)">{{ t('Edit Event') }}</a>
                    </li>
                    <li v-if="adminUrl" role="none">
                        <a role="menuitem" class="fe-event-menu__item" :href="adminUrl" @click.stop>{{ t('View in FluentEvent') }}</a>
                    </li>
                </ul>
            </div>
        </div>
    `,
};
