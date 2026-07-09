<template>
    <el-dropdown
        size="large"
        trigger="click"
        @command="setTheme"
        class="theme-dropdown"
    >
        <span class="el-dropdown-link">
            <el-icon size="20" :color="themeColor">
                <component :is="themeIcon" />
            </el-icon>
        </span>
        <template #dropdown>
            <el-dropdown-menu>
                <el-dropdown-item 
                    command="light" 
                    :class="{ 'is-active': currentTheme === 'light' }"
                >
                    <el-icon size="16" style="margin-right: 8px; color: #606266">
                        <Sunny />
                    </el-icon>
                    Light
                </el-dropdown-item>

                <el-dropdown-item 
                    command="dark" 
                    :class="{ 'is-active': currentTheme === 'dark' }"
                >
                    <el-icon size="16" style="margin-right: 8px; color: #606266">
                        <Moon />
                    </el-icon>
                    Dark
                </el-dropdown-item>

                <el-dropdown-item 
                    command="system" 
                    :class="{ 'is-active': currentTheme === 'system' }"
                >
                    <el-icon size="16" style="margin-right: 8px; color: #606266">
                        <Monitor />
                    </el-icon>
                    System
                </el-dropdown-item>
            </el-dropdown-menu>
        </template>
    </el-dropdown>
</template>

<script>
import 'element-plus/theme-chalk/dark/css-vars.css';
import { Monitor, Moon, Sunny } from "@element-plus/icons-vue";
import Storage from '@/utils/Storage';

export default {
    name: "ThemeSwitcher",
    components: { Moon, Sunny, Monitor },
    data() {
        return {
            currentTheme: "system",
            colorVariables: {
                bgColor: '--el-bg-color',
                primary: '--el-color-primary',
                success: '--el-color-success',
                warning: '--el-color-warning',
                danger: '--el-color-danger',
                info: '--el-color-info',

                // Default button
                defaultButtonBg: '--el-button-bg-color',
                defaultButtonHoverBg: '--el-button-hover-bg-color',
                defaultButtonDisabledBg: '--el-button-disabled-bg-color',

                // Primary
                primaryButtonBg: '--el-color-primary',
                primaryButtonHoverBg: '--el-color-primary-light-3',
                primaryButtonDisabledBg: '--el-color-primary-light-5',

                // Success
                successButtonBg: '--el-color-success',
                successButtonHoverBg: '--el-color-success-light-3',
                successButtonDisabledBg: '--el-color-success-light-5',

                // Danger
                dangerButtonBg: '--el-color-danger',
                dangerButtonHoverBg: '--el-color-danger-light-3',
                dangerButtonDisabledBg: '--el-color-danger-light-5',

                // Info
                infoButtonBg: '--el-color-info',
                infoButtonHoverBg: '--el-color-info-light-3',
                infoButtonDisabledBg: '--el-color-info-light-5',

                // Warning
                warningButtonBg: '--el-color-warning',
                warningButtonHoverBg: '--el-color-warning-light-3',
                warningButtonDisabledBg: '--el-color-warning-light-5',
            },
        };
    },
    computed: {
        themeIcon() {
            switch (this.currentTheme) {
                case "light": return Sunny;
                case "dark": return Moon;
                default: return Monitor;
            }
        },
        themeColor() {
            return 'var(--el-color-primary)';
        },
    },
    methods: {
        /**
         * Switch the active theme to `light`, `dark`, or `system`. Writes the
         * choice to `Storage`, applies the matching palette to root + wpbody,
         * and broadcasts a `theme-changed` window event for components that
         * need to react imperatively.
         * @param {('light'|'dark'|'system')} mode
         */
        setTheme(mode) {
            if (typeof mode !== "string") {
                return;
            }
            
            if (!["light", "dark", "system"].includes(mode)) {
                return;
            }

            this.currentTheme = mode;

            const root = document.documentElement;
            const wpbody = document.querySelector("#wpbody");

            this.setColorScheme(mode, root, wpbody);

            root.classList.remove("dark", "light");
            wpbody?.classList.remove("dark", "light");

            if (mode === "light") {
                root.classList.add("light");
                wpbody?.classList.add("light");
                Storage.set("theme", "light");
            } else if (mode === "dark") {
                root.classList.add("dark");
                wpbody?.classList.add("dark");
                Storage.set("theme", "dark");
            } else {
                Storage.set("theme", "system");
                this.watchSystemTheme();
            }

            window.dispatchEvent(
                new CustomEvent('theme-changed', {
                    detail: {
                        isDark: this.isDark(),
                        scheme: this.currentTheme,
                    }
                })
            );
        },
        /**
         * Apply the palette for `mode` onto `root` + `wpbody` by setting every
         * `--el-*` CSS variable from the app config's theme object, then
         * derives matching surface variables (backgrounds, borders, text)
         * from the base `bgColor`.
         * @param {('light'|'dark'|'system')} mode
         * @param {HTMLElement} root
         * @param {HTMLElement|null} wpbody
         */
        setColorScheme(mode, root, wpbody) {
            const scheme = this.getColorScheme(mode);
            
            if (!scheme) return;

            Object.entries(scheme).forEach(([key, value]) => {
                const cssVar = this.colorVariables[key] || `--${key}`;

                root.style.setProperty(cssVar, value);

                if (wpbody) {
                    wpbody.style.setProperty(cssVar, value);
                }
            });

            const resolvedMode = mode === 'system'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : mode;

            this.setSurfaceVariables(resolvedMode, scheme.bgColor, root, wpbody);
        },
        /**
         * Derive and apply Element Plus surface variables (bg, borders,
         * fills, text colors) from the palette's base `bgColor`. Dark mode
         * computes lighter shades via `adjustBrightness()`; light mode uses
         * Element Plus's standard light palette.
         * @param {('light'|'dark')} mode
         * @param {string} bgColor  Hex color.
         * @param {HTMLElement} root
         * @param {HTMLElement|null} wpbody
         */
        setSurfaceVariables(mode, bgColor, root, wpbody) {
            if (!bgColor) return;

            let vars = {};

            if (mode === 'dark') {
                const bg = bgColor;
                vars = {
                    '--el-bg-color-page': this.adjustBrightness(bg, -10),
                    '--el-bg-color-overlay': this.adjustBrightness(bg, 12),
                    '--el-text-color-primary': '#e5eaf3',
                    '--el-text-color-regular': '#cfd3dc',
                    '--el-text-color-secondary': '#a3a6ad',
                    '--el-text-color-placeholder': '#8d9095',
                    '--el-text-color-disabled': '#6c6e72',
                    '--el-border-color': this.adjustBrightness(bg, 30),
                    '--el-border-color-light': this.adjustBrightness(bg, 22),
                    '--el-border-color-lighter': this.adjustBrightness(bg, 16),
                    '--el-border-color-extra-light': this.adjustBrightness(bg, 10),
                    '--el-fill-color': this.adjustBrightness(bg, 18),
                    '--el-fill-color-light': this.adjustBrightness(bg, 12),
                    '--el-fill-color-lighter': this.adjustBrightness(bg, 6),
                    '--el-fill-color-dark': this.adjustBrightness(bg, 24),
                    '--el-fill-color-darker': this.adjustBrightness(bg, 30),
                    '--el-fill-color-extra-light': this.adjustBrightness(bg, 3),
                    '--el-fill-color-blank': bg,
                };
            } else {
                vars = {
                    '--el-bg-color-page': bgColor,
                    '--el-bg-color-overlay': '#ffffff',
                    '--el-text-color-primary': '#303133',
                    '--el-text-color-regular': '#606266',
                    '--el-text-color-secondary': '#909399',
                    '--el-text-color-placeholder': '#a8abb2',
                    '--el-text-color-disabled': '#c0c4cc',
                    '--el-border-color': '#dcdfe6',
                    '--el-border-color-light': '#e4e7ed',
                    '--el-border-color-lighter': '#ebeef5',
                    '--el-border-color-extra-light': '#f2f6fc',
                    '--el-fill-color': '#f0f2f5',
                    '--el-fill-color-light': '#f5f7fa',
                    '--el-fill-color-lighter': '#fafafa',
                    '--el-fill-color-dark': '#ebedf0',
                    '--el-fill-color-darker': '#e6e8eb',
                    '--el-fill-color-extra-light': '#fafcff',
                    '--el-fill-color-blank': '#ffffff',
                };
            }

            Object.entries(vars).forEach(([cssVar, value]) => {
                root.style.setProperty(cssVar, value);
                if (wpbody) {
                    wpbody.style.setProperty(cssVar, value);
                }
            });
        },
        /**
         * Shift a hex color by `amount` across every channel (clamped to
         * 0-255). Negative darkens, positive lightens. Used to derive dark-mode
         * surface tones from a single base background color.
         * @param {string} hex
         * @param {number} amount
         * @returns {string}  `#rrggbb`
         */
        adjustBrightness(hex, amount) {
            hex = hex.replace('#', '');
            let r = Math.min(255, Math.max(0, parseInt(hex.substring(0, 2), 16) + amount));
            let g = Math.min(255, Math.max(0, parseInt(hex.substring(2, 4), 16) + amount));
            let b = Math.min(255, Math.max(0, parseInt(hex.substring(4, 6), 16) + amount));
            return '#' + [r, g, b].map(c => c.toString(16).padStart(2, '0')).join('');
        },
        /**
         * Resolve the concrete `light`/`dark` scheme object from `appVars.theme`,
         * expanding `system` by inspecting the OS preference.
         * @param {('light'|'dark'|'system')} mode
         * @returns {Object|undefined}
         */
        getColorScheme(mode) {
            if (mode === 'system') {
                mode = window.matchMedia(
                    '(prefers-color-scheme: dark)'
                ).matches ? 'dark' : 'light';
            }
            if (this.appVars.theme) {
                return this.appVars.theme[mode];
            }
        },
        /**
         * Subscribe to OS-level `prefers-color-scheme` changes so the UI
         * auto-flips when the user toggles system dark mode. No-op unless
         * the current theme is set to `system`.
         */
        watchSystemTheme() {
            if (this.currentTheme !== 'system') return;

            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

            this.applySystemTheme(mediaQuery.matches);

            mediaQuery.addEventListener('change', (e) => {
                this.applySystemTheme(e.matches);
            });
        },
        /**
         * Apply the light/dark classes + scheme based on the OS's dark-mode
         * preference. Called by the `prefers-color-scheme` media-query
         * listener when `system` theme is active.
         * @param {boolean} isDark
         */
        applySystemTheme(isDark) {
            const root = document.documentElement;
            const wpbody = document.querySelector("#wpbody");

            root.classList.remove('light', 'dark');

            wpbody?.classList.remove('light', 'dark');

            if (isDark) {
                root.classList.add('dark');
                wpbody?.classList.add('dark');
            } else {
                root.classList.add('light');
                wpbody?.classList.add('light');
            }

            this.setColorScheme('system', root, wpbody);

            window.dispatchEvent(
                new CustomEvent('os-theme-changed', {
                    detail: {
                        isDark: this.isDark(),
                        scheme: this.currentTheme,
                    }
                })
            );
        },
        /**
         * Current effective theme polarity — resolves `system` to the OS
         * preference. Useful for consumers that need a concrete boolean
         * rather than the tri-state `currentTheme`.
         * @returns {boolean}
         */
        isDark() {
            if (this.currentTheme === 'system') {
                return window.matchMedia('(prefers-color-scheme: dark)').matches;
            }

            return this.currentTheme === 'dark';
        },
    },
    mounted() {
        this.currentTheme = Storage.get("theme") || "system";
        this.setTheme(this.currentTheme);
        this.watchSystemTheme();
    },
};
</script>

<style scoped>
.theme-dropdown {
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 15px;
}
</style>
