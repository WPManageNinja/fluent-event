<template>
    <div class="fe-media-picker">
        <img
            v-if="safeSrc"
            class="fe-media-picker__image"
            :src="safeSrc"
            :alt="alt"
        />

        <slot v-else name="placeholder" />

        <button
            type="button"
            class="fe-media-picker__surface"
            :aria-label="pickLabel"
            :title="pickLabel"
            @click="openLibrary"
        ></button>

        <slot v-if="safeSrc && removable" name="remove" :remove="removeSelection">
            <el-button
                class="fe-media-picker__remove"
                circle
                :aria-label="removeLabel"
                :title="removeLabel"
                @click="removeSelection"
            >
                <el-icon><Delete /></el-icon>
            </el-button>
        </slot>
    </div>
</template>

<script type="text/javascript">
    import { Delete } from '@element-plus/icons-vue';

    export default {
        name: 'MediaPicker',
        components: {
            Delete,
        },
        props: {
            modelValue: {
                type: String,
                default: '',
            },
            frameTitle: {
                type: String,
                default: '',
            },
            actionLabel: {
                type: String,
                default: '',
            },
            libraryType: {
                type: String,
                default: 'image',
            },
            alt: {
                type: String,
                default: '',
            },
            addLabel: {
                type: String,
                default: '',
            },
            changeLabel: {
                type: String,
                default: '',
            },
            removeLabel: {
                type: String,
                default: '',
            },
            removable: {
                type: Boolean,
                default: true,
            },
        },
        emits: ['update:modelValue'],
        data() {
            return {
                frame: null,
            };
        },
        computed: {
            safeSrc() {
                return /^https?:/i.test(this.modelValue) ? this.modelValue : '';
            },
            pickLabel() {
                const label = this.safeSrc ? this.changeLabel : this.addLabel;
                return label || this.$t('Select media');
            },
        },
        methods: {
            createFrame() {
                if (typeof window.wp?.media !== 'function') return;

                this.frame = window.wp.media({
                    title: this.frameTitle || this.$t('Select media'),
                    button: { text: this.actionLabel || this.$t('Use this media') },
                    library: { type: this.libraryType },
                    multiple: false,
                });

                this.frame.on('select', this.onSelect);
                this.frame.once('open', this.paintLibrary);
            },
            paintLibrary() {
                const library = this.frame.state()?.get('library');
                if (!library) return;
                library.more().done(() => library.trigger('reset'));
            },
            openLibrary() {
                if (!this.frame) {
                    this.createFrame();
                }

                if (!this.frame) {
                    this.$notifyError(this.$t('The WordPress media library is unavailable.'));
                    return;
                }
                this.frame.open();
            },
            onSelect() {
                const attachment = this.frame.state().get('selection').first();
                this.$emit('update:modelValue', attachment?.toJSON()?.url || '');
            },
            removeSelection() {
                this.$emit('update:modelValue', '');
            },
        },
        beforeUnmount() {
            this.frame?.off('select', this.onSelect);
            this.frame?.off('open', this.paintLibrary);
            this.frame?.remove?.();
        },
    };
</script>
