<template>
    <div class="fe-cover-picker">
        <img
            v-if="safeSrc"
            class="fe-cover-picker__image"
            :src="safeSrc"
            :alt="$t('Event cover image')"
        />

        <div v-else class="fe-cover-picker__placeholder">
            <el-icon class="fe-cover-picker__placeholder-icon" aria-hidden="true">
                <Picture />
            </el-icon>
            <span class="fe-cover-picker__placeholder-text">
                {{ $t('No cover image yet') }}
            </span>
        </div>

        <div class="fe-cover-picker__actions">
            <el-button
                v-if="safeSrc"
                class="fe-cover-picker__action"
                circle
                :aria-label="$t('Remove cover image')"
                :title="$t('Remove cover image')"
                @click="removeCover"
            >
                <el-icon><Delete /></el-icon>
            </el-button>

            <el-button
                class="fe-cover-picker__action"
                circle
                :aria-label="pickLabel"
                :title="pickLabel"
                @click="openLibrary"
            >
                <el-icon><Edit /></el-icon>
            </el-button>
        </div>
    </div>
</template>

<script type="text/javascript">
    import { Picture, Edit, Delete } from '@element-plus/icons-vue';

    export default {
        name: 'EventCoverPicker',
        components: {
            Picture,
            Edit,
            Delete,
        },
        props: {
            modelValue: {
                type: String,
                default: '',
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
                return this.safeSrc ? this.$t('Change cover image') : this.$t('Add a cover image');
            },
        },
        methods: {
            openLibrary() {
                if (!window.wp?.media) {
                    this.$notifyError(this.$t('The WordPress media library is unavailable.'));
                    return;
                }

                if (!this.frame) {
                    this.frame = window.wp.media({
                        title: this.$t('Select a cover image'),
                        button: { text: this.$t('Use this image') },
                        library: { type: 'image' },
                        multiple: false,
                    });

                    this.frame.on('select', this.onSelect);
                }

                this.frame.open();
            },
            onSelect() {
                const attachment = this.frame.state().get('selection').first();

                this.$emit('update:modelValue', attachment?.toJSON()?.url || '');
            },
            removeCover() {
                this.$emit('update:modelValue', '');
            },
        },
        beforeUnmount() {
            this.frame?.off('select', this.onSelect);
            this.frame?.remove?.();
        },
    };
</script>
