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
                    <dd class="fe-event-settings__value">{{ capacity || $t('Unlimited') }}</dd>
                </div>

                <div class="fe-event-settings__row">
                    <dt class="fe-event-settings__label">{{ $t('Guest count') }}</dt>
                    <dd class="fe-event-settings__value">{{ countShown ? $t('Shown') : $t('Hidden') }}</dd>
                </div>

                <div v-if="capacity" class="fe-event-settings__row">
                    <dt class="fe-event-settings__label">{{ $t('Spots left') }}</dt>
                    <dd class="fe-event-settings__value">{{ event.meta?.show_spots_left === 'yes' ? $t('Shown') : $t('Hidden') }}</dd>
                </div>

                <div v-if="capacity" class="fe-event-settings__row">
                    <dt class="fe-event-settings__label">{{ $t('Waitlist') }}</dt>
                    <dd class="fe-event-settings__value">{{ event.meta?.waitlist === 'yes' ? $t('On') : $t('Off') }}</dd>
                </div>

                <div class="fe-event-settings__row">
                    <dt class="fe-event-settings__label">{{ $t('Registration opens') }}</dt>
                    <dd class="fe-event-settings__value">
                        {{ registrationWindow?.opens_at ? wallClock(registrationWindow.opens_at) : $t('When published') }}
                    </dd>
                </div>

                <div class="fe-event-settings__row">
                    <dt class="fe-event-settings__label">{{ $t('Registration closes') }}</dt>
                    <dd class="fe-event-settings__value">
                        {{ registrationWindow?.closes_at ? wallClock(registrationWindow.closes_at) : $t('When the event ends') }}
                    </dd>
                </div>
            </template>
        </dl>
    </div>
</template>

<script type="text/javascript">
    import dayjs from 'dayjs';
    import utc from 'dayjs/plugin/utc';
    import { EditPen } from '@element-plus/icons-vue';

    dayjs.extend(utc);

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
            // The event API's registration_window: opens_at and closes_at in site time, worked out on the server.
            registrationWindow: {
                type: Object,
                default: null,
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
            countShown() {
                return this.event.meta?.show_guest_count !== 'no';
            },
        },
        methods: {
            // The site's wall-clock as sent; read in the browser's zone, a time its clocks skip would show an hour on.
            wallClock(at) {
                return this.$formatDateTime(dayjs.utc(at));
            },
        },
    };
</script>
