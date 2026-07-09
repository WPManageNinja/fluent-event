import { getCurrentInstance } from 'vue'

/**
 * Expose the framework's i18n helpers (`$t`, `$n`) to `<script setup>`
 * components — Options API components can use `this.$t` directly, but
 * composition-style files need this bridge since the helpers are mounted on
 * the component instance.
 *
 * @returns {{ $t: (key: string, replacements?: Object) => string, $n: (value: number) => string }}
 */
export function useTranslation() {
    const { proxy } = getCurrentInstance()
    return { $t: proxy.$t, $n: proxy.$n }
}
