import * as Icons from '@element-plus/icons-vue';
import { markRaw } from 'vue';

const iconMap = {};

for (const [name, icon] of Object.entries(Icons)) {
    iconMap[name] = markRaw(icon);
}

/**
 * Look up an Element Plus icon component by name (e.g. `HomeFilled`).
 * Returns `null` for unknown names so `<component :is="...">` renders
 * nothing rather than crashing.
 *
 * @param {string} name
 * @returns {Object|null}
 */
export function resolveIcon(name) {
    return iconMap[name] || null;
}
