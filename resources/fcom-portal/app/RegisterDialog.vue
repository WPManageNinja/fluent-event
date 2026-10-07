<template>
    <el-dialog
        v-model="visible"
        class="fe-register-dialog"
        :title="$t('Register for %s', eventTitle)"
        width="440px"
        append-to-body
        @opened="$refs.nameInput.focus()"
        @closed="$emit('closed')"
    >
        <form id="fe-register-dialog-form" class="fe-register-dialog__form" @submit.prevent="submit">
            <el-form-item :label="$t('Name')" label-position="top" required>
                <el-input ref="nameInput" v-model="name" maxlength="255" autocomplete="name"/>
            </el-form-item>

            <el-form-item :label="$t('Email')" label-position="top" required>
                <el-input v-model="email" type="email" maxlength="192" autocomplete="email"/>
            </el-form-item>

            <p v-if="error" class="fe-register-dialog__error" role="alert">{{ error }}</p>
        </form>

        <template #footer>
            <el-button class="fcom_secondary_button" :disabled="saving" @click="visible = false">
                {{ $t('Cancel') }}
            </el-button>

            <el-button
                class="fcom_primary_button"
                native-type="submit"
                form="fe-register-dialog-form"
                :loading="saving"
            >
                {{ $t('Register') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script>
import { eventsApi, errorMessage, portalVars, t } from '../utils';

export default {
    name: 'RegisterDialog',
    props: {
        eventId: { type: Number, required: true },
        eventTitle: { type: String, default: '' },
    },
    emits: ['done', 'closed'],
    data() {
        const user = portalVars().user || {};

        return {
            visible: true,
            name: user.name || '',
            email: user.email || '',
            saving: false,
            error: '',
        };
    },
    methods: {
        async submit() {
            if (!this.name.trim() || !this.email.trim()) {
                this.error = t('Please fill in the required fields.');
                return;
            }

            const payload = { name: this.name, email: this.email };

            if (!portalVars().is_logged_in) {
                payload._nonce = portalVars().register_nonce;
            }

            this.saving = true;
            this.error = '';

            try {
                this.$emit('done', await eventsApi.post(`community/events/${this.eventId}/register`, payload));
                this.visible = false;
            } catch (error) {
                this.error = errorMessage(error);
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>
