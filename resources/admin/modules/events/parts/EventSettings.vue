<template>
    <div class="fe-card fe-event-settings">
        <div class="fe-event-settings__header">
            <h2 class="fe-event-settings__title">{{ $t('Settings') }}</h2>

            <el-button size="small" @click="$router.push({ name: 'event_edit', params: { id: event.id } })">
                <el-icon aria-hidden="true"><EditPen /></el-icon>
                <span>{{ $t('Edit') }}</span>
            </el-button>
        </div>

        <dl class="fe-event-settings__list">
            <div class="fe-event-settings__row">
                <dt class="fe-event-settings__label">{{ $t('Start Time Display') }}</dt>
                <dd class="fe-event-settings__value">
                    {{ event.meta?.start_display === 'countdown' ? $t('Countdown') : $t('Time Left') }}
                </dd>
            </div>

            <div class="fe-event-settings__row">
                <dt class="fe-event-settings__label">{{ $t('Entry Type') }}</dt>
                <dd class="fe-event-settings__value">{{ $entryTypeLabel(entryType) }}</dd>
            </div>

            <template v-if="takesRegistrations">
                <div class="fe-event-settings__row">
                    <dt class="fe-event-settings__label">{{ $t('Capacity') }}</dt>
                    <dd class="fe-event-settings__value">
                        <span>{{ capacity || $t('Unlimited') }}</span>

                        <!-- Seats are going and checked-in guests, as the capacity counts them; admins can add past it. -->
                        <span v-if="capacity" class="fe-event-settings__meter">
                            <span
                                class="fe-event-settings__track"
                                role="progressbar"
                                aria-valuemin="0"
                                :aria-valuemax="capacity"
                                :aria-valuenow="seats"
                                :aria-label="$t('Capacity')"
                            >
                                <span class="fe-event-settings__fill" :style="{ width: `${seatsPercent}%` }"></span>
                            </span>
                            <span class="fe-event-settings__meter-status">{{ seatsLeftLabel }}</span>
                        </span>
                    </dd>
                </div>

                <div class="fe-event-settings__row">
                    <dt class="fe-event-settings__label">{{ $t('Guest count') }}</dt>
                    <dd class="fe-event-settings__value">{{ countShown ? $t('Shown') : $t('Hidden') }}</dd>
                </div>

                <div v-if="capacity" class="fe-event-settings__row">
                    <dt class="fe-event-settings__label">{{ $t('Spots left') }}</dt>
                    <dd class="fe-event-settings__value">{{ event.meta?.show_spots_left === 'yes' ? $t('Shown') : $t('Hidden') }}</dd>
                </div>
            </template>
        </dl>
    </div>
</template>

<script type="text/javascript">
    import { EditPen } from '@element-plus/icons-vue';

    export default {
        name: 'EventSettings',
        components: {
            EditPen,
        },
        props: {
            event: {
                type: Object,
                required: true,
            },
        },
        computed: {
            entryType() {
                return this.event.meta?.entry_type || 'none';
            },
            takesRegistrations() {
                return ['rsvp', 'open'].includes(this.entryType);
            },
            capacity() {
                return parseInt(this.event.meta?.capacity, 10) || 0;
            },
            seats() {
                return this.event.seats_count || 0;
            },
            seatsPercent() {
                return Math.min(100, Math.round((this.seats / this.capacity) * 100));
            },
            seatsLeftLabel() {
                const left = this.capacity - this.seats;

                return left > 0 ? this.$_n('%d spot left', '%d spots left', left) : this.$t('Full');
            },
            countShown() {
                return this.event.meta?.show_guest_count !== 'no';
            },
        },
    };
</script>
