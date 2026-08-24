<template>
    <el-popover
        ref="popoverRef"
        trigger="manual"
        :visible="visible"
        :placement="placement"
        :width="width"
        :persistent="persistent"
        :teleported="false"
        popper-class="fe-action-popover"
        @after-enter="focusFirstItem"
    >
        <template #reference>
            <span
                ref="triggerRef"
                class="fe-action-popover__trigger"
                :tabindex="hasCustomTrigger ? 0 : undefined"
                :role="hasCustomTrigger ? 'button' : undefined"
                :aria-expanded="hasCustomTrigger ? visible : undefined"
                :aria-haspopup="hasCustomTrigger ? 'menu' : undefined"
                @click="toggle"
                @keydown.enter="onTriggerKey"
                @keydown.space="onTriggerKey"
            >
                <slot name="trigger">
                    <el-button
                        text
                        class="fe-action-popover__trigger-button"
                        size="small"
                        :aria-label="$t('View actions')"
                        :title="$t('View actions')"
                        :aria-expanded="visible"
                        aria-haspopup="menu"
                    >
                        <el-icon><MoreFilled /></el-icon>
                    </el-button>
                </slot>
            </span>
        </template>

        <slot :hide="hide">
            <ul
                class="fe-action-popover__menu"
                role="menu"
                aria-orientation="vertical"
                @keydown.esc.prevent.stop="hideAndFocusTrigger"
            >
                <li
                    v-for="(item, index) in items"
                    :key="index"
                    ref="menuItems"
                    class="fe-action-popover__item"
                    :class="item.type ? `fe-action-popover__item--${item.type}` : ''"
                    role="menuitem"
                    tabindex="0"
                    @click="handleAction(item)"
                    @keydown.enter.prevent="handleAction(item)"
                    @keydown.space.prevent="handleAction(item)"
                    @keydown.down.prevent="focusItem(index + 1)"
                    @keydown.up.prevent="focusItem(index - 1)"
                >
                    {{ item.label }}
                </li>
            </ul>
        </slot>
    </el-popover>
</template>

<script type="text/javascript">
    import { MoreFilled } from '@element-plus/icons-vue';

    export default {
        name: 'ActionPopover',
        components: {
            MoreFilled,
        },
        props: {
            // [{ label, action, type }] — `type` = danger, success, warning.
            items: {
                type: Array,
                default: () => [],
            },
            width: {
                type: [Number, String],
                default: 150,
            },
            placement: {
                type: String,
                default: 'bottom',
            },
            persistent: {
                type: Boolean,
                default: false,
            },
        },
        emits: ['show', 'hide'],
        computed: {
            hasCustomTrigger() {
                return !!this.$slots.trigger;
            },
        },
        data() {
            return {
                visible: false,
            };
        },
        watch: {
            visible(val) {
                this.$emit(val ? 'show' : 'hide');

                const method = val ? 'addEventListener' : 'removeEventListener';
                document[method]('click', this.onClickOutside, true);
            },
        },
        methods: {
            toggle() {
                this.visible = !this.visible;
            },
            onTriggerKey(e) {
                if (e.target !== this.$refs.triggerRef) return;

                e.preventDefault();
                this.toggle();
            },
            hide() {
                this.visible = false;
            },
            onClickOutside(e) {
                const content = this.$refs.popoverRef?.popperRef?.contentRef;

                if (content?.contains(e.target)) return;
                if (this.$refs.triggerRef?.contains(e.target)) return;
                // A nested popper (select, confirm) is still "inside" this menu.
                if (e.target.closest?.('.el-popper')) return;

                this.hide();
            },
            handleAction(item) {
                this.hide();

                if (item.action) {
                    item.action();
                }
            },
            focusFirstItem() {
                this.focusItem(0);
            },
            focusItem(index) {
                const items = this.$refs.menuItems;
                if (!Array.isArray(items) || !items.length) return;
                items[(index + items.length) % items.length]?.focus?.();
            },
            hideAndFocusTrigger() {
                this.hide();
                this.$nextTick(() => {
                    const trigger = this.$refs.triggerRef;
                    const focusable = trigger?.querySelector?.('button, a, [tabindex]') || trigger;
                    focusable?.focus?.();
                });
            },
        },
        beforeUnmount() {
            document.removeEventListener('click', this.onClickOutside, true);
        },
    };
</script>
