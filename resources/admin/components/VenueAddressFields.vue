<template>
    <div class="fe-venue-fields">
        <el-form-item :label="$t('City')" label-position="top" :for="`${idPrefix}-city`">
            <el-input :id="`${idPrefix}-city`" :model-value="city" maxlength="100" @update:model-value="$emit('update:city', $event)" />
        </el-form-item>

        <el-form-item :label="$t('ZIP code')" label-position="top" :for="`${idPrefix}-zip-code`">
            <el-input :id="`${idPrefix}-zip-code`" :model-value="zipCode" maxlength="20" @update:model-value="$emit('update:zipCode', $event)" />
        </el-form-item>

        <el-form-item class="fe-venue-fields__wide" :label="$t('Country')" label-position="top" :for="`${idPrefix}-country`">
            <el-input :id="`${idPrefix}-country`" :model-value="country" maxlength="100" @update:model-value="$emit('update:country', $event)" />
        </el-form-item>

        <el-form-item class="fe-venue-fields__wide" :label="$t('Map link')" label-position="top" :for="`${idPrefix}-map-url`">
            <el-input
                :id="`${idPrefix}-map-url`"
                :model-value="mapUrl"
                type="url"
                placeholder="https://maps.google.com/..."
                @update:model-value="$emit('update:mapUrl', $event)"
            />
            <span class="fe-venue-fields__hint">{{ $t('A Google Maps or other map link for guests.') }}</span>
        </el-form-item>
    </div>
</template>

<script type="text/babel">
// The optional address parts a saved venue and a one-off venue share.
export default {
    name: 'VenueAddressFields',
    props: {
        city: { type: String, default: '' },
        zipCode: { type: String, default: '' },
        country: { type: String, default: '' },
        mapUrl: { type: String, default: '' },
        idPrefix: { type: String, required: true }
    },
    emits: ['update:city', 'update:zipCode', 'update:country', 'update:mapUrl']
};
</script>
