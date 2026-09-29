<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 158" fill="none" aria-hidden="true">
        <rect class="fe-view-picker__frame" x="0.5" y="0.5" width="159" height="157" rx="5.5" stroke="var(--fe-primary-border, #eaecf0)"/>

        <template v-if="name === 'cards'">
            <rect x="12.5" y="12.5" width="63" height="62" rx="3.5" stroke="var(--fe-primary-border, #eaecf0)"/>
            <rect x="20" y="20" width="48" height="27" rx="4" fill="var(--fe-active-bg, #f0f0f1)"/>
            <rect x="20" y="53" width="48" height="6" rx="3" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="20" y="63" width="24" height="6" rx="3" fill="var(--fe-secondary-border, #9CA3AF)"/>

            <rect x="84.5" y="12.5" width="63" height="62" rx="3.5" stroke="var(--fe-primary-border, #eaecf0)"/>
            <rect x="92" y="20" width="48" height="27" rx="4" fill="var(--fe-active-bg, #f0f0f1)"/>
            <rect x="92" y="53" width="48" height="6" rx="3" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="92" y="63" width="24" height="6" rx="3" fill="var(--fe-secondary-border, #9CA3AF)"/>

            <rect x="12.5" y="83.5" width="63" height="62" rx="3.5" stroke="var(--fe-primary-border, #eaecf0)"/>
            <rect x="20" y="91" width="48" height="27" rx="4" fill="var(--fe-active-bg, #f0f0f1)"/>
            <rect x="20" y="124" width="48" height="6" rx="3" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="20" y="134" width="24" height="6" rx="3" fill="var(--fe-secondary-border, #9CA3AF)"/>

            <rect x="84.5" y="83.5" width="63" height="62" rx="3.5" stroke="var(--fe-primary-border, #eaecf0)"/>
            <rect x="92" y="91" width="48" height="27" rx="4" fill="var(--fe-active-bg, #f0f0f1)"/>
            <rect x="92" y="124" width="48" height="6" rx="3" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="92" y="134" width="24" height="6" rx="3" fill="var(--fe-secondary-border, #9CA3AF)"/>
        </template>

        <template v-else-if="name === 'timeline'">
            <line x1="44" y1="16" x2="44" y2="144" stroke="var(--fe-primary-border, #eaecf0)" stroke-dasharray="3 3"/>

            <rect x="12" y="20" width="22" height="5" rx="2.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="12" y="29" width="15" height="4" rx="2" fill="var(--fe-active-bg, #f0f0f1)"/>
            <circle cx="44" cy="22" r="3.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="54.5" y="12.5" width="93" height="37" rx="3.5" stroke="var(--fe-primary-border, #eaecf0)"/>
            <rect x="61" y="19" width="24" height="24" rx="3" fill="var(--fe-active-bg, #f0f0f1)"/>
            <rect x="91" y="22" width="48" height="6" rx="3" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="91" y="33" width="30" height="5" rx="2.5" fill="var(--fe-active-bg, #f0f0f1)"/>

            <rect x="12" y="66" width="22" height="5" rx="2.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="12" y="75" width="15" height="4" rx="2" fill="var(--fe-active-bg, #f0f0f1)"/>
            <circle cx="44" cy="68" r="3.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="54.5" y="58.5" width="93" height="37" rx="3.5" stroke="var(--fe-primary-border, #eaecf0)"/>
            <rect x="61" y="65" width="24" height="24" rx="3" fill="var(--fe-active-bg, #f0f0f1)"/>
            <rect x="91" y="68" width="48" height="6" rx="3" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="91" y="79" width="30" height="5" rx="2.5" fill="var(--fe-active-bg, #f0f0f1)"/>

            <rect x="12" y="112" width="22" height="5" rx="2.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="12" y="121" width="15" height="4" rx="2" fill="var(--fe-active-bg, #f0f0f1)"/>
            <circle cx="44" cy="114" r="3.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="54.5" y="104.5" width="93" height="37" rx="3.5" stroke="var(--fe-primary-border, #eaecf0)"/>
            <rect x="61" y="111" width="24" height="24" rx="3" fill="var(--fe-active-bg, #f0f0f1)"/>
            <rect x="91" y="114" width="48" height="6" rx="3" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="91" y="125" width="30" height="5" rx="2.5" fill="var(--fe-active-bg, #f0f0f1)"/>
        </template>

        <template v-else-if="name === 'calendar'">
            <rect x="12" y="14" width="40" height="6" rx="3" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="116" y="13" width="14" height="8" rx="2" fill="var(--fe-active-bg, #f0f0f1)"/>
            <rect x="134" y="13" width="14" height="8" rx="2" fill="var(--fe-active-bg, #f0f0f1)"/>

            <rect x="12.5" y="30.5" width="135" height="115" rx="3.5" stroke="var(--fe-primary-border, #eaecf0)"/>
            <path d="M31.8 31v114M51.1 31v114M70.4 31v114M89.6 31v114M108.9 31v114M128.2 31v114" stroke="var(--fe-primary-border, #eaecf0)"/>
            <path d="M13 53.5h134M13 76.5h134M13 99.5h134M13 122.5h134" stroke="var(--fe-primary-border, #eaecf0)"/>

            <rect x="34.5" y="42" width="14" height="5" rx="2.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="73" y="65" width="14" height="5" rx="2.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="92.3" y="65" width="14" height="5" rx="2.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="15.2" y="88" width="14" height="5" rx="2.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="111.6" y="88" width="14" height="5" rx="2.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="53.8" y="111" width="14" height="5" rx="2.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="130.9" y="111" width="14" height="5" rx="2.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="34.5" y="134" width="14" height="5" rx="2.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
            <rect x="92.3" y="134" width="14" height="5" rx="2.5" fill="var(--fe-secondary-border, #9CA3AF)"/>
        </template>
    </svg>
</template>

<script type="text/javascript">
    export default {
        name: 'Icon',
        props: {
            name: {
                type: String,
                required: true,
                validator: value => ['cards', 'timeline', 'calendar'].includes(value),
            },
        },
    };
</script>
