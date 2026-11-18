<template>
    <el-dialog
        :model-value="modelValue"
        width="560px"
        append-to-body
        class="fe-share-modal"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <template #header="{ titleId, titleClass }">
            <span :id="titleId" :class="[titleClass, 'fe-share-modal__title']" role="heading" aria-level="2">
                <el-icon aria-hidden="true"><Share /></el-icon>
                {{ $t('Share Event') }}
            </span>
        </template>

        <p v-if="note" class="fe-share-modal__note">{{ note }}</p>

        <Tabs v-else v-model="tab" :tabs="tabs" :sync-route="false">
            <template #shortcode>
                <p class="fe-share-modal__text">
                    {{ $t('Add this shortcode to any page or post to show this event.') }}
                </p>

                <el-button class="fe-share-modal__shortcode" :title="$t('Copy shortcode')" @click="copyShortcode">
                    <el-icon aria-hidden="true"><CopyDocument /></el-icon>
                    <span class="fe-share-modal__shortcode-text">{{ shortcode }}</span>
                </el-button>
            </template>

            <template #link>
                <p class="fe-share-modal__text">
                    {{ $t('Anyone with this link can open the event page.') }}
                </p>

                <div class="fe-share-modal__link">
                    <!-- Text, not a readonly input: other plugins force their own readonly-input background with !important. -->
                    <span class="fe-share-modal__url">{{ publicUrl }}</span>

                    <el-button tag="a" :href="publicUrl" target="_blank" rel="noopener">
                        {{ $t('View') }}
                    </el-button>

                    <el-button @click="copyPublicUrl">
                        {{ $t('Copy Link') }}
                    </el-button>
                </div>
            </template>

            <template #block>
                <p class="fe-share-modal__text">
                    {{ $t('In the block editor, add the FluentEvent Single Event block, then pick this event in its settings.') }}
                </p>

                <img
                    class="fe-share-modal__image"
                    :src="appVars.block_inserter_image"
                    :alt="$t('The block inserter with the FluentEvent Single Event block selected')"
                />
            </template>
        </Tabs>

        <template #footer>
            <el-button @click="$emit('update:modelValue', false)">
                {{ $t('Close') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script type="text/javascript">
    import { CopyDocument, Share } from '@element-plus/icons-vue';
    import Tabs from '@/components/Tabs.vue';

    export default {
        name: 'EventShareModal',
        components: {
            CopyDocument,
            Share,
            Tabs,
        },
        props: {
            modelValue: {
                type: Boolean,
                required: true,
            },
            event: {
                type: Object,
                required: true,
            },
            publicUrl: {
                type: String,
                default: '',
            },
            shortcode: {
                type: String,
                default: '',
            },
        },
        emits: ['update:modelValue'],
        data() {
            return {
                tab: 'link',
            };
        },
        computed: {
            tabs() {
                return [
                    { value: 'link', label: this.$t('Public Page') },
                    { value: 'shortcode', label: this.$t('Shortcode') },
                    { value: 'block', label: this.$t('Add to Block') },
                ];
            },
            // The shortcode and block render only what the public page shows, so both need the page.
            note() {
                if (!['public', 'unlisted'].includes(this.event.status)) {
                    return this.$t('Only public and unlisted events have a public page.');
                }

                return this.event.calendar?.status === 'public'
                    ? ''
                    : this.$t('Its calendar is not public, so this event has no public page.');
            },
        },
        methods: {
            copyShortcode() {
                navigator.clipboard.writeText(this.shortcode)
                    .then(() => this.$notifySuccess(this.$t('Shortcode copied.')))
                    .catch(() => this.$notifyError(this.$t('The shortcode could not be copied.')));
            },
            copyPublicUrl() {
                navigator.clipboard.writeText(this.publicUrl)
                    .then(() => this.$notifySuccess(this.$t('Link copied.')))
                    .catch(() => this.$notifyError(this.$t('The link could not be copied.')));
            },
        },
    };
</script>
