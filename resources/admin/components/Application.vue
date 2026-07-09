<template>
    <el-config-provider :locale="elLocale">
        <el-container class="layout">
            <!-- Top Navigation -->
            <el-header class="navbar">
                <!-- Left Section (logo + menu) -->
                <div class="navbar-left">
                    <!-- Brand Logo -->
                    <div class="brand-logo">
                        <a href="/">
                            <img :src="logoUrl" alt="Brand" class="brand-logo" />
                            <span>beta</span>
                        </a>
                    </div>

                    <!-- Left Menu -->
                    <Nav
                        class="menu"
                        v-if="primaryMenu.length"
                        :routes="primaryMenu"
                    />
                </div>

                <!-- Right Menu -->
                <div class="navbar-right">
                    <ThemeSwitcher />
                    <Nav
                        v-if="secondaryMenu.length"
                        :routes="secondaryMenu"
                        :key="secondaryMenu[0]?.meta?.label"
                        class="menu"
                    />
                </div>
            </el-header>

            <!-- Page Content -->
            <el-main class="main">
                <router-view v-slot="{ Component, route }">
                    <!-- Dynamically render the routed component -->
                    <component
                        :is="Component"
                        :key="route.fullPath"
                        v-on="{ 'update:application': updateApplication }"
                    />
                </router-view>

                <!--- Render Footer Menu -->
                <div class="footer-menu">
                    <Nav
                        v-if="footerMenu.length"
                        :routes="footerMenu"
                    />
                </div>
            </el-main>
        </el-container>
    </el-config-provider>
</template>

<script>
import Rest from '@/utils/http/Rest';
import Nav from '@/components/Menu/Menu.vue';
import menu from '@/components/Menu/menu.js';
import ThemeSwitcher from '@/components/ThemeSwitcher';
import en from 'element-plus/dist/locale/en.mjs';

// Element Plus locale map — uncomment locales as you add language support.
// Only uncommented entries are bundled. English is always available
// (using the static import above).
const elLocaleMap = {
    // en_US: () => import('element-plus/dist/locale/en.mjs'),
    // af_ZA: () => import('element-plus/dist/locale/af.mjs'),
    // ar: () => import('element-plus/dist/locale/ar.mjs'),
    // az_AZ: () => import('element-plus/dist/locale/az.mjs'),
    // bg_BG: () => import('element-plus/dist/locale/bg.mjs'),
    // bn_BD: () => import('element-plus/dist/locale/bn.mjs'),
    // ca: () => import('element-plus/dist/locale/ca.mjs'),
    // cs_CZ: () => import('element-plus/dist/locale/cs.mjs'),
    // da_DK: () => import('element-plus/dist/locale/da.mjs'),
    // de_DE: () => import('element-plus/dist/locale/de.mjs'),
    // el: () => import('element-plus/dist/locale/el.mjs'),
    // eo: () => import('element-plus/dist/locale/eo.mjs'),
    // es_ES: () => import('element-plus/dist/locale/es.mjs'),
    // et_EE: () => import('element-plus/dist/locale/et.mjs'),
    // eu: () => import('element-plus/dist/locale/eu.mjs'),
    // fa_IR: () => import('element-plus/dist/locale/fa.mjs'),
    // fi: () => import('element-plus/dist/locale/fi.mjs'),
    // fr_FR: () => import('element-plus/dist/locale/fr.mjs'),
    // he_IL: () => import('element-plus/dist/locale/he.mjs'),
    // hi_IN: () => import('element-plus/dist/locale/hi.mjs'),
    // hr: () => import('element-plus/dist/locale/hr.mjs'),
    // hu_HU: () => import('element-plus/dist/locale/hu.mjs'),
    // hy_AM: () => import('element-plus/dist/locale/hy-am.mjs'),
    // id_ID: () => import('element-plus/dist/locale/id.mjs'),
    // it_IT: () => import('element-plus/dist/locale/it.mjs'),
    // ja: () => import('element-plus/dist/locale/ja.mjs'),
    // ka_GE: () => import('element-plus/dist/locale/ka.mjs'),
    // km_KH: () => import('element-plus/dist/locale/km.mjs'),
    // ko_KR: () => import('element-plus/dist/locale/ko.mjs'),
    // ku: () => import('element-plus/dist/locale/ku.mjs'),
    // ky_KY: () => import('element-plus/dist/locale/ky.mjs'),
    // lt_LT: () => import('element-plus/dist/locale/lt.mjs'),
    // lv_LV: () => import('element-plus/dist/locale/lv.mjs'),
    // mk_MK: () => import('element-plus/dist/locale/mk.mjs'),
    // mn_MN: () => import('element-plus/dist/locale/mn.mjs'),
    // ms_MY: () => import('element-plus/dist/locale/ms.mjs'),
    // nb_NO: () => import('element-plus/dist/locale/nb-no.mjs'),
    // nl_NL: () => import('element-plus/dist/locale/nl.mjs'),
    // pa_IN: () => import('element-plus/dist/locale/pa.mjs'),
    // pl_PL: () => import('element-plus/dist/locale/pl.mjs'),
    // pt_PT: () => import('element-plus/dist/locale/pt.mjs'),
    // pt_BR: () => import('element-plus/dist/locale/pt-br.mjs'),
    // ro_RO: () => import('element-plus/dist/locale/ro.mjs'),
    // ru_RU: () => import('element-plus/dist/locale/ru.mjs'),
    // sk_SK: () => import('element-plus/dist/locale/sk.mjs'),
    // sl_SI: () => import('element-plus/dist/locale/sl.mjs'),
    // sq_AL: () => import('element-plus/dist/locale/sq.mjs'),
    // sr_RS: () => import('element-plus/dist/locale/sr.mjs'),
    // sv_SE: () => import('element-plus/dist/locale/sv.mjs'),
    // ta_IN: () => import('element-plus/dist/locale/ta.mjs'),
    // te_IN: () => import('element-plus/dist/locale/te.mjs'),
    // th: () => import('element-plus/dist/locale/th.mjs'),
    // tk: () => import('element-plus/dist/locale/tk.mjs'),
    // tr_TR: () => import('element-plus/dist/locale/tr.mjs'),
    // ug_CN: () => import('element-plus/dist/locale/ug.mjs'),
    // uk_UA: () => import('element-plus/dist/locale/uk.mjs'),
    // ur_PK: () => import('element-plus/dist/locale/ur.mjs'),
    // uz_UZ: () => import('element-plus/dist/locale/uz.mjs'),
    // vi: () => import('element-plus/dist/locale/vi.mjs'),
    // zh_CN: () => import('element-plus/dist/locale/zh-cn.mjs'),
    // zh_TW: () => import('element-plus/dist/locale/zh-tw.mjs'),
};

export default {
    name: "Application",
    components: {
        Nav,
        ThemeSwitcher,
    },
    props: {
        logoUrl: {
            type: String,
            required: true
        },
        baseUrl: {
            type: String,
            required: true
        }
    },
    data() {
        return {
            primaryMenu: menu.get('primary') || [],
            secondaryMenu: menu.get('secondary') || [],
            footerMenu: menu.get('footer') || [],
            elLocale: en,
        };
    },
    provide() {
        return {
            // ...
        };
    },
    computed: {
        // ...
    },
    methods: {
        /**
         * Register a Rest-client middleware that stamps every outbound REST
         * request with the `X-Fluent` identifier header. Customize or append
         * handlers here (auth tokens, telemetry headers, etc.) before the
         * app boots.
         */
        registerRestRequestInterceptor() {
            Rest.use((options, next) => {
                options.headers = {
                    ...options.headers,
                    'X-Fluent': 'Fluent-Custom-Header',
                };

                return next(options);
            });
        },
        /**
         * Hook for post-boot application updates — called after the initial
         * load when server-side data arrives. Left as a no-op stub for
         * plugins to override with their own refresh logic.
         *
         * @param {*} [data]  Payload from the caller.
         */
        updateApplication(data = null) {
            // ...
        },
        /**
         * Dynamically load the Element Plus locale bundle matching WP's
         * `user_locale`. Falls back silently to English if the bundle isn't
         * available or the import fails — keeps the app mountable even on
         * sites with unusual locales.
         */
        async loadElementLocale() {
            const wpLocale = this.appVars?.user_locale || 'en_US';
            const loader = elLocaleMap[wpLocale];
            if (loader) {
                try {
                    const mod = await loader();
                    this.elLocale = mod.default;
                } catch {
                    // Fallback to English
                }
            }
        },
    },
    async mounted() {
        this.registerRestRequestInterceptor();
        await this.loadElementLocale();
    }
};
</script>

<style>
ul.el-pager {
    margin-top: 6px !important;
}
</style>
