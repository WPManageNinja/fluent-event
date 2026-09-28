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
                :class="{ 'fe-action-popover__trigger--active': visible }"
                :tabindex="triggerHasControl ? undefined : 0"
                :role="triggerHasControl ? undefined : 'button'"
                :aria-expanded="triggerHasControl ? undefined : visible"
                :aria-haspopup="triggerHasControl ? undefined : 'menu'"
                @click="toggle"
                @keydown.enter="onTriggerKey"
                @keydown.space="onTriggerKey"
            >
                <slot name="trigger" :visible="visible">
                    <el-button
                        text
                        class="fe-action-popover__trigger-button"
                        size="small"
                        :aria-label="triggerLabel"
                        :title="triggerLabel"
                        :aria-expanded="visible"
                        aria-haspopup="menu"
                    >
                        <el-icon><MoreFilled /></el-icon>
                    </el-button>
                </slot>
            </span>
        </template>

        <div ref="menuRef" @keydown="onMenuKey">
            <slot :hide="hide">
                <ul class="fe-action-popover__menu" role="menu" aria-orientation="vertical">
                    <li
                        v-for="(item, index) in items"
                        :key="index"
                        class="fe-action-popover__item"
                        :class="item.type ? `fe-action-popover__item--${item.type}` : ''"
                        role="menuitem"
                        tabindex="0"
                        @click="handleAction(item)"
                        @keydown.enter.prevent="handleAction(item)"
                        @keydown.space.prevent="handleAction(item)"
                    >
                        {{ item.label }}
                    </li>
                </ul>
            </slot>
        </div>
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
            // What the actions act on, e.g. a guest's name — tells rows apart.
            subject: {
                type: String,
                default: '',
            },
        },
        emits: ['show', 'hide'],
        computed: {
            triggerLabel() {
                return this.subject
                    ? this.$t('Actions for %s', this.subject)
                    : this.$t('View actions');
            },
        },
        data() {
            return {
                visible: false,
                triggerHasControl: true,
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
            menuItems() {
                return [...(this.$refs.menuRef?.querySelectorAll('[role="menuitem"]') || [])];
            },
            focusItem(index) {
                const items = this.menuItems();
                if (!items.length) return;
                items[(index + items.length) % items.length].focus();
            },
            onMenuKey(e) {
                const index = this.menuItems().indexOf(e.target);
                if (index === -1) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.focusItem(index + 1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.focusItem(index - 1);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    this.hideAndFocusTrigger();
                }
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
        mounted() {
            this.triggerHasControl = !!this.$refs.triggerRef.querySelector(
                'button, a[href], input, select, textarea, [tabindex]'
            );
        },
        beforeUnmount() {
            document.removeEventListener('click', this.onClickOutside, true);
        },
    };
</script>
