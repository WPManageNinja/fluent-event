<template>
    <transition name="fade" mode="out-in" appear>
        <div class="fe-page fe-page--narrow">
            <Skeleton v-if="loading" type="calendar-form" />

            <ErrorState v-else-if="error" :message="error" @retry="fetchCalendar" />

            <template v-else>
                <div class="fe-page__header">
                    <div>
                        <h1 class="fe-page__title">{{ pageTitle }}</h1>
                        <p class="fe-page__subtitle">{{ pageSubtitle }}</p>
                    </div>

                    <div class="fe-page__actions">
                        <el-button @click="$goBack">
                            {{ $t('Go Back') }}
                        </el-button>
                    </div>
                </div>

                <div class="fe-card fe-card--flush">
                    <el-form
                        class="fe-form fe-form--sectioned"
                        label-position="top"
                        @submit.prevent="saveCalendar"
                    >
                        <section class="fe-form__section">
                            <div class="fe-form__section-rail">
                                <h2 class="fe-form__section-title">{{ $t('Identity') }}</h2>
                                <p class="fe-form__section-desc">
                                    {{ $t('The name and color people see wherever this calendar appears.') }}
                                </p>
                            </div>

                            <div class="fe-form__section-fields">
                                <el-form-item :label="$t('Title')" required>
                                    <el-input
                                        v-model="calendar.title"
                                        :placeholder="$t('e.g. Personal')"
                                        @input="deriveSlug"
                                    />
                                </el-form-item>

                                <el-form-item :label="$t('Slug')">
                                    <el-input
                                        ref="slug"
                                        v-model="calendar.slug"
                                        :placeholder="$t('e.g. personal')"
                                        :disabled="slugLocked"
                                        @input="onSlugInput"
                                        @blur="normalizeSlug"
                                    >
                                        <template v-if="slugLocked" #append>
                                            <el-button @click="unlockSlug">{{ $t('Change') }}</el-button>
                                        </template>
                                    </el-input>
                                    <span class="fe-form__hint">
                                        {{ $t('Identifies this calendar in links. Changing it later breaks existing ones.') }}
                                    </span>
                                </el-form-item>

                                <el-form-item :label="$t('Color')">
                                    <div
                                        class="fe-form__swatches"
                                        role="group"
                                        :aria-label="$t('Theme color')"
                                    >
                                        <button
                                            v-for="color in colorOptions"
                                            :key="color.value"
                                            type="button"
                                            class="fe-form__swatch"
                                            :class="{
                                                'fe-form__swatch--selected':
                                                    calendar.theme_color === color.value,
                                            }"
                                            :style="{ backgroundColor: color.value }"
                                            :aria-label="color.label"
                                            :aria-pressed="calendar.theme_color === color.value"
                                            @click="calendar.theme_color = color.value"
                                        ></button>

                                        <el-config-provider :locale="pickerLocale">
                                            <el-color-picker
                                                :model-value="calendar.theme_color"
                                                class="fe-form__swatch fe-form__swatch--picker"
                                                :clearable="false"
                                                :aria-label="$t('Add a custom color')"
                                                @change="selectColor"
                                            />
                                        </el-config-provider>
                                    </div>
                                </el-form-item>
                            </div>
                        </section>

                        <section class="fe-form__section">
                            <div class="fe-form__section-rail">
                                <h2
                                    id="fe-calendar-description-label"
                                    class="fe-form__section-title"
                                >
                                    {{ $t('Description') }}
                                </h2>
                                <p class="fe-form__section-desc">
                                    {{ $t('Optional. Shown on the calendar page, above its events.') }}
                                </p>
                            </div>

                            <div class="fe-form__section-fields">
                                <WpEditor
                                    v-model="calendar.description"
                                    :height="280"
                                    media-buttons
                                    quick-tags
                                    aria-labelledby="fe-calendar-description-label"
                                />
                            </div>
                        </section>

                        <section class="fe-form__section">
                            <div class="fe-form__section-rail">
                                <h2 class="fe-form__section-title">{{ $t('Visibility') }}</h2>
                                <p class="fe-form__section-desc">
                                    {{ $t('Choose who can find this calendar.') }}
                                </p>
                            </div>

                            <div class="fe-form__section-fields">
                                <el-radio-group
                                    v-model="calendar.status"
                                    class="fe-form__choices"
                                    :aria-label="$t('Status')"
                                >
                                    <el-radio
                                        v-for="option in $calendarStatuses()"
                                        :key="option.value"
                                        :value="option.value"
                                        border
                                    >
                                        <span class="fe-form__choice-title">{{ option.label }}</span>
                                        <span class="fe-form__hint">{{ option.hint }}</span>
                                    </el-radio>
                                </el-radio-group>
                            </div>
                        </section>

                        <div class="fe-form__actions fe-form__actions--bar">
                            <el-button native-type="button" :disabled="saving" @click="resetForm">
                                {{ $t('Reset Calendar') }}
                            </el-button>
                            <el-button
                                type="primary"
                                native-type="submit"
                                :loading="saving"
                                :disabled="saving"
                            >
                                {{ submitLabel }}
                            </el-button>
                        </div>
                    </el-form>
                </div>
            </template>
        </div>
    </transition>
</template>

<script type="text/javascript">
    import en from 'element-plus/dist/locale/en.mjs';
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
                slugLocked: false,
                calendar: {},
                original: {},
                customColors: [],
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
            pickerLocale() {
                return {
                    ...en,
                    el: {
                        ...en.el,
                        colorpicker: { ...en.el.colorpicker, confirm: this.$t('Apply') },
                    },
                };
            },
            colorOptions() {
                return [
                    ...this.themeColors,
                    ...this.customColors.map(value => ({ value, label: value })),
                ];
            },
        },
        methods: {
            initForm() {
                return {
                    title: '',
                    slug: '',
                    description: '',
                    status: this.$calendarStatuses()[0].value,
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
                            status: response.calendar.status,
                            theme_color: response.calendar.meta?.theme_color?.toLowerCase()
                                || this.themeColors[0].value,
                        };
                        this.selectColor(this.calendar.theme_color);
                        this.original = { ...this.calendar };
                        this.slugEdited = true;
                        this.slugLocked = true;
                    },
                });
            },
            selectColor(color) {
                if (!this.colorOptions.some(option => option.value === color)) {
                    this.customColors.push(color);
                }

                this.calendar.theme_color = color;
            },
            deriveSlug() {
                if (!this.slugEdited) {
                    this.calendar.slug = this.$slugify(this.calendar.title);
                }
            },
            unlockSlug() {
                this.$confirm(
                    this.$t('Existing links to this calendar will be affected. Do you still want to change the slug?'),
                    this.$t('Change slug'),
                    {
                        confirmButtonText: this.$t('Change slug'),
                        cancelButtonText: this.$t('Cancel'),
                        type: 'warning',
                    }
                ).then(() => {
                    this.slugLocked = false;
                    this.$nextTick(() => this.$refs.slug.focus());
                }).catch(() => {});
            },
            onSlugInput(value) {
                const slug = this.$slugify(value);
                this.calendar.slug = slug && /[\s-]$/.test(value) ? `${slug}-` : slug;
                this.slugEdited = slug !== '';
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
                    this.slugLocked = true;
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
