<template>
    <el-dialog
        :model-value="modelValue"
        :title="$t('Guest Details')"
        width="460px"
        append-to-body
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <div v-if="guest" class="fe-guest-details">
            <div class="fe-guest-details__identity">
                <img
                    v-if="guest.avatar"
                    class="fe-guest-details__avatar"
                    :src="guest.avatar"
                    :alt="guest.name"
                />

                <div>
                    <p class="fe-guest-details__name">{{ guest.name }}</p>
                    <p class="fe-guest-details__email">{{ guest.email }}</p>
                </div>
            </div>

            <dl class="fe-guest-details__list">
                <div class="fe-guest-details__row">
                    <dt class="fe-guest-details__label">{{ $t('Status') }}</dt>

                    <dd class="fe-guest-details__value">
                        <el-tag :type="guest.tagType" size="small">
                            {{ guest.statusLabel }}
                        </el-tag>
                    </dd>
                </div>

                <div class="fe-guest-details__row">
                    <dt class="fe-guest-details__label">{{ $t('Source') }}</dt>

                    <dd class="fe-guest-details__value">
                        {{ guest.source ? $guestSourceLabel(guest.source) : $t('Not set') }}
                    </dd>
                </div>

                <div class="fe-guest-details__row">
                    <dt class="fe-guest-details__label">{{ $t('Responded') }}</dt>

                    <dd class="fe-guest-details__value">
                        {{ $formatDateTime(guest.created_at) }}
                    </dd>
                </div>

                <div v-if="guest.checked_in_at" class="fe-guest-details__row">
                    <dt class="fe-guest-details__label">{{ $t('Checked in') }}</dt>

                    <dd class="fe-guest-details__value">
                        {{ $formatDateTime(guest.checked_in_at) }}
                    </dd>
                </div>

                <div class="fe-guest-details__row">
                    <dt class="fe-guest-details__label">{{ $t('Last updated') }}</dt>

                    <dd class="fe-guest-details__value">
                        {{ $formatDateTime(guest.updated_at) }}
                    </dd>
                </div>

                <div class="fe-guest-details__row">
                    <dt class="fe-guest-details__label">{{ $t('WP User') }}</dt>

                    <dd class="fe-guest-details__value">
                        {{ guest.user_id ? $t('Yes') : $t('No') }}
                    </dd>
                </div>
            </dl>
        </div>

        <template #footer>
            <el-button @click="$emit('update:modelValue', false)">
                {{ $t('Close') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script type="text/javascript">
    export default {
        name: 'GuestDetailsModal',
        props: {
            modelValue: {
                type: Boolean,
                required: true,
            },
            guest: {
                type: Object,
                default: null,
            },
        },
        emits: ['update:modelValue'],
    };
</script>
