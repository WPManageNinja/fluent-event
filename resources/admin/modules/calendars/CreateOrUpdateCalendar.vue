<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page">
            <Skeleton v-if="loading" type="calendar-form" />

            <ErrorState v-else-if="error" :message="error" @retry="fetchCalendar" />

            <template v-else>
                <div class="fe-page__header">
                    <div>
                        <h1 class="fe-page__title">{{ pageTitle }}</h1>
                        <p class="fe-page__subtitle">{{ pageSubtitle }}</p>
                    </div>

                    <div class="fe-page__actions">
                        <el-button type="secondary" @click="$goBack">
                            {{ $t('Go Back') }}
                        </el-button>
                    </div>
                </div>

                <div class="fe-card">
                    <el-form class="fe-form" label-position="top" @submit.prevent="saveCalendar">
                        <el-form-item :label="$t('Title *')">
                            <el-input
                                v-model="calendar.title"
                                :placeholder="$t('e.g. Personal')"
                                @input="deriveSlug"
                            >
                                <template #prepend>
                                    <el-select
                                        v-model="calendar.theme_color"
                                        :aria-label="$t('Theme color')"
                                        popper-class="fe-color-dropdown"
                                    >
                                        <template #prefix>
                                            <span
                                                class="fe-color-swatch"
                                                :style="{ background: calendar.theme_color }"
                                            ></span>
                                        </template>

                                        <el-option
                                            v-for="color in themeColors"
                                            :key="color.value"
                                            :label="color.label"
                                            :value="color.value"
                                        >
                                            <span
                                                class="fe-color-swatch"
                                                :style="{ background: color.value }"
                                            ></span>
                                        </el-option>
                                    </el-select>
                                </template>
                            </el-input>
                        </el-form-item>

                        <el-form-item :label="$t('Slug *')">
                            <el-input
                                v-model="calendar.slug"
                                :placeholder="$t('e.g. personal')"
                                @input="onSlugInput"
                                @blur="normalizeSlug"
                            />
                        </el-form-item>

                        <el-form-item :label="$t('Description')">
                            <WpEditor v-model="calendar.description" :height="180" />
                        </el-form-item>

                        <el-row :gutter="20">
                            <el-col :md="12" :sm="24">
                                <el-form-item :label="$t('Visibility')">
                                    <el-radio-group v-model="calendar.visibility">
                                        <el-radio
                                            v-for="option in visibilities"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </el-radio>
                                    </el-radio-group>
                                </el-form-item>
                            </el-col>

                            <el-col :md="12" :sm="24">
                                <el-form-item :label="$t('Status')">
                                    <el-radio-group v-model="calendar.status">
                                        <el-radio
                                            v-for="option in statuses"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </el-radio>
                                    </el-radio-group>
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <div class="fe-form__actions">
                            <el-button
                                type="primary"
                                native-type="submit"
                                :loading="saving"
                                :disabled="saving"
                            >
                                {{ submitLabel }}
                            </el-button>
                            <el-button native-type="button" :disabled="saving" @click="resetForm">
                                {{ $t('Reset') }}
                            </el-button>
                        </div>
                    </el-form>
                </div>
            </template>
        </div>
    </transition>
</template>

<script type="text/javascript">
    import WpEditor from '@/components/WpEditor.vue';
    import ErrorState from '@/components/ErrorState.vue';
    import Skeleton from '@/components/Skeleton.vue';

    export default {
        name: 'CreateOrUpdateCalendar',
        components: {
            WpEditor,
            ErrorState,
            Skeleton,
        },
        data() {
            return {
                loading: false,
                error: '',
                saving: false,
                slugEdited: false,
                calendar: {},
                original: {},
            };
        },
        computed: {
            calendarId() {
                return this.$route.params.id;
            },
            isEditing() {
                return !!this.calendarId;
            },
            pageTitle() {
                return this.isEditing ? this.$t('Edit Calendar') : this.$t('Create Calendar');
            },
            pageSubtitle() {
                return this.isEditing
                    ? this.$t('Update the calendar details. Events already in it are not affected.')
                    : this.$t('A calendar holds your events. Give it a title and you can start adding events to it.');
            },
            submitLabel() {
                return this.isEditing ? this.$t('Update Calendar') : this.$t('Create Calendar');
            },
            themeColors() {
                return [
                    { value: '#ff4f00', label: this.$t('Red-Orange') },
                    { value: '#e55cff', label: this.$t('Deep Lilac') },
                    { value: '#8247f5', label: this.$t('Purple') },
                    { value: '#0099ff', label: this.$t('Vivid Blue') },
                    { value: '#0ae8f0', label: this.$t('Cyan') },
                    { value: '#17e885', label: this.$t('Emerald Green') },
                    { value: '#ccf000', label: this.$t('Lime Green') },
                    { value: '#ffa600', label: this.$t('Amber') },
                ];
            },
            visibilities() {
                return [
                    { value: 'public', label: this.$t('Public') },
                    { value: 'private', label: this.$t('Private') },
                    { value: 'unlisted', label: this.$t('Unlisted') },
                ];
            },
            statuses() {
                return [
                    { value: 'active', label: this.$t('Active') },
                    { value: 'draft', label: this.$t('Draft') },
                    { value: 'archived', label: this.$t('Archived') },
                ];
            },
        },
        methods: {
            initForm() {
                return {
                    title: '',
                    slug: '',
                    description: '',
                    visibility: this.visibilities[0].value,
                    status: this.statuses[0].value,
                    theme_color: this.themeColors[0].value,
                };
            },
            fetchCalendar() {
                if (!this.isEditing) return;

                this.$requestServer({
                    path: `calendars/${this.calendarId}`,
                    onSuccess: response => {
                        this.error = '';
                        this.calendar = {
                            title: response.calendar.title,
                            slug: response.calendar.slug,
                            description: response.calendar.description,
                            visibility: response.calendar.visibility,
                            status: response.calendar.status,
                            theme_color: response.calendar.meta?.theme_color
                                || this.themeColors[0].value,
                        };
                        this.original = { ...this.calendar };
                        this.slugEdited = true;
                    },
                });
            },
            deriveSlug() {
                if (!this.slugEdited) {
                    this.calendar.slug = this.$slugify(this.calendar.title);
                }
            },
            onSlugInput(value) {
                this.slugEdited = String(value).trim() !== '';
            },
            normalizeSlug() {
                this.calendar.slug = this.$slugify(this.calendar.slug);
            },
            saveCalendar() {
                this.$requestServer({
                    method: this.isEditing ? 'patch' : 'post',
                    path: this.isEditing ? `calendars/${this.calendarId}` : 'calendars',
                    payload: {
                        title: this.calendar.title,
                        slug: this.$slugify(this.calendar.slug),
                        description: this.calendar.description,
                        visibility: this.calendar.visibility,
                        status: this.calendar.status,
                        theme_color: this.calendar.theme_color,
                    },
                    loadingState: 'saving',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => {
                        this.$router.push({
                            name: 'calendar_detail',
                            params: { id: this.calendarId || response.calendar.id },
                        });
                    },
                });
            },
            resetForm() {
                if (this.isEditing) {
                    this.calendar = { ...this.original };
                    this.slugEdited = true;
                    return;
                }

                this.calendar = this.initForm();
                this.slugEdited = false;
            },
        },
        created() {
            this.calendar = this.initForm();
            if (this.isEditing) {
                this.fetchCalendar();
            }
        },
        mounted() {
            this.$changeTitle(this.pageTitle);
        },
    };
</script>
