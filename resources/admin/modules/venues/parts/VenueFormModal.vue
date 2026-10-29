<template>
    <el-dialog
        :model-value="modelValue"
        :title="title"
        width="520px"
        append-to-body
        @update:model-value="$emit('update:modelValue', $event)"
        @open="resetForm"
    >
        <el-form class="fe-form" label-position="top" @submit.prevent="save">
            <el-form-item :label="$t('Name *')" for="fe-venue-name">
                <el-input
                    id="fe-venue-name"
                    v-model="form.name"
                    maxlength="255"
                    :placeholder="$t('e.g. Kingsway Hall')"
                />
            </el-form-item>

            <el-form-item :label="$t('Address *')" for="fe-venue-address">
                <el-input
                    id="fe-venue-address"
                    v-model="form.address"
                    type="textarea"
                    :rows="2"
                    maxlength="500"
                    :placeholder="$t('Street and number')"
                />
            </el-form-item>

            <VenueAddressFields
                v-model:city="form.city"
                v-model:zip-code="form.zip_code"
                v-model:country="form.country"
                v-model:map-url="form.map_url"
                id-prefix="fe-venue"
            />
        </el-form>

        <template #footer>
            <el-button :disabled="saving" @click="close">
                {{ $t('Cancel') }}
            </el-button>

            <el-button type="primary" :loading="saving" :disabled="saving" @click="save">
                {{ submitLabel }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script type="text/javascript">
    import VenueAddressFields from '@/components/VenueAddressFields.vue';

    const FIELDS = ['name', 'address', 'city', 'zip_code', 'country', 'map_url'];

    export default {
        name: 'VenueFormModal',
        components: {
            VenueAddressFields,
        },
        props: {
            modelValue: {
                type: Boolean,
                required: true,
            },
            venue: {
                type: Object,
                default: null,
            },
        },
        emits: ['update:modelValue', 'saved'],
        data() {
            return {
                saving: false,
                form: {},
            };
        },
        computed: {
            isEditing() {
                return !!this.venue;
            },
            title() {
                return this.isEditing ? this.$t('Edit Venue') : this.$t('Add Venue');
            },
            submitLabel() {
                return this.isEditing ? this.$t('Update Venue') : this.$t('Add Venue');
            },
        },
        methods: {
            resetForm() {
                this.form = Object.fromEntries(FIELDS.map(field => [field, this.venue?.[field] || '']));
            },
            close() {
                this.$emit('update:modelValue', false);
            },
            save() {
                this.$requestServer({
                    method: this.isEditing ? 'patch' : 'post',
                    path: this.isEditing ? `venues/${this.venue.id}` : 'venues',
                    payload: this.form,
                    loadingState: 'saving',
                    errorState: null,
                    successNotify: true,
                    errorNotify: true,
                    onSuccess: response => {
                        this.close();
                        this.$emit('saved', response.venue);
                    },
                });
            },
        },
    };
</script>
