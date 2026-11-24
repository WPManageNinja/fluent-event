export default class Storage {
    static #prefix = 'fevent_';

    static get(key, defaultValue = null) {
        try {
            const value = localStorage.getItem(Storage.#prefix + key);
            return value === null ? defaultValue : JSON.parse(value);
        } catch (e) {
            return defaultValue;
        }
    }

    static set(key, value) {
        try {
            localStorage.setItem(Storage.#prefix + key, JSON.stringify(value));
        } catch (e) {
            // Storage blocked or full: the preference just isn't remembered.
        }
    }
}
