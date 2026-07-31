import config from '@/bootstrap/config'

/**
 * Expose the localized framework config (`fluentEventAdmin`) to a Vue
 * component as a plain object plus a dotted-key `getConfig()` reader. The
 * config is the server's single source of truth for REST URL, nonces, the
 * endpoint map, and the current user — prefer this over reading the global
 * directly so tests can inject a mock config.
 *
 * @returns {{ config: Object, getConfig: (key: string, defaultValue?: *) => * }}
 */
export function useConfig() {
    return {
        config,
        getConfig: (key, defaultValue = null) => config.get(key, defaultValue),
    }
}
