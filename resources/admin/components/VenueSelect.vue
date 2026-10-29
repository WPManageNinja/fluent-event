<template>
    <el-form-item :label="$t('Venue')" label-position="top" :for="id" required>
        <el-select
            :id="id"
            :model-value="selected ? modelValue : null"
            class="fe-venue-select"
            popper-class="fe-venue-select__popper"
            fit-input-width
            filterable
            clearable
            :placeholder="$t('Choose a saved venue')"
            @update:model-value="pick"
        >
            <el-option v-for="venue in venues" :key="venue.id" :label="venue.name" :value="venue.id">
                <span class="fe-venue-select__name">{{ venue.name }}</span>
                <span class="fe-venue-select__address">{{ fullAddress(venue) }}</span>
            </el-option>
        </el-select>

        <div v-if="selected" class="fe-venue-select__summary">
            <span>{{ fullAddress(selected) }}</span>

            <a
                v-if="mapUrl(selected)"
                class="fe-venue-select__map"
                :href="mapUrl(selected)"
                target="_blank"
                rel="noopener noreferrer"
            >
                {{ $t('Open map') }}
            </a>
        </div>

    </el-form-item>
</template>

<script type="text/babel">
import { fullAddress, mapUrl } from '@/utils/venue';

export default {
    name: 'VenueSelect',
    props: {
        modelValue: {
            type: Number,
            default: null
        },
        venues: {
            type: Array,
            required: true
        },
        id: {
            type: String,
            required: true
        }
    },
    emits: ['update:modelValue'],
    computed: {
        selected() {
            return this.venues.find(venue => venue.id === this.modelValue) || null;
        }
    },
    methods: {
        fullAddress,
        mapUrl,
        // Clearing the select gives '', and the form means "no saved venue" by null.
        pick(venueId) {
            this.$emit('update:modelValue', venueId || null);
        }
    }
};
</script>
