import { describe, it, expect } from 'vitest';
import fs from 'fs';
import { fileURLToPath } from 'url';
import { createRouter, createMemoryHistory } from 'vue-router';
import routes from '@/router/routes';

const read = (relative) => fs.readFileSync(fileURLToPath(new URL(relative, import.meta.url)), 'utf8');
const menuHandler = read('../../../app/Hooks/Handlers/AdminMenuHandler.php');
const permissionManager = read('../../../app/Services/PermissionManager.php');

const methodBody = (php, name) => php.slice(php.indexOf(`function ${name}(`), php.indexOf('function ', php.indexOf(`function ${name}(`) + 9));

// [key, path] for each topbar entry; '' is the dashboard, which links to the bare base URL.
const menuEntries = (php) => [...php.matchAll(
    /'key'\s*=>\s*'(\w+)',\s*'label'\s*=>\s*__\([^)]*\),\s*'permalink'\s*=>\s*\$this->baseUrl(?:\s*\.\s*'([^']*)')?/g
)].map(([, key, path]) => [key, path || '']);

const centerItems = menuEntries(methodBody(menuHandler, 'getMenuItemsList'));
const rightItems = menuEntries(methodBody(menuHandler, 'getRightMenuItemsList'));
const dropdownParent = methodBody(menuHandler, 'getRightMenuItemsList').match(/'key'\s*=>\s*'(\w+)'[^\]]*'children'\s*=>/)[1];
const wrapperItems = [...centerItems, ...rightItems.filter(([key]) => key === dropdownParent)];
const dropdownItems = rightItems.filter(([key]) => key !== dropdownParent);

const grantable = [...permissionManager.matchAll(/'(\w+)'\s*=>\s*__\(/g)].map(([, key]) => key).concat('manage_all_data');

const flatten = (list, parent = '') => list.flatMap(route => {
    const path = route.path.startsWith('/') ? route.path : `${parent}/${route.path}`;
    return [{ ...route, path }, ...flatten(route.children || [], path)];
});
const allRoutes = flatten(routes);
const pageRoutes = allRoutes.filter(route => route.name && !['unauthorized', 'notfound'].includes(route.name));
const router = createRouter({ history: createMemoryHistory(), routes });

describe('admin routes', () => {
    it('finds the topbar entries in AdminMenuHandler', () => {
        // Guards the regexes: a refactor of the PHP must fail here, not pass every test below vacuously.
        expect(centerItems.map(([key]) => key)).toEqual(['dashboard', 'calendars', 'events']);
        expect(dropdownParent).toBe('settings');
        expect(dropdownItems.length).toBeGreaterThanOrEqual(3);
    });

    it('gives every page an active_menu', () => {
        pageRoutes.forEach(route => {
            expect(route.meta?.active_menu, route.name).toBeTruthy();
        });
    });

    it('only names active_menu keys the PHP topbar renders as a wrapper', () => {
        // start.js highlights `.fe-navbar__link-wrapper[data-key="<active_menu>"]`.
        const wrapperKeys = wrapperItems.map(([key]) => key);

        pageRoutes.forEach(route => {
            expect(wrapperKeys, route.name).toContain(route.meta.active_menu);
        });
    });

    it('opens the page each topbar link points to under its own active_menu', () => {
        wrapperItems.forEach(([key, path]) => {
            expect(router.resolve(`/${path}`).meta.active_menu, key).toBe(key);
        });
    });

    it('names each settings dropdown route after the link that highlights it', () => {
        // start.js matches the dropdown link's data-key against to.name.
        dropdownItems.forEach(([key, path]) => {
            expect(router.resolve(`/${path}`).name).toBe(key);
        });
    });

    it('gates each topbar page with the same keys the PHP menu checks', () => {
        const gates = [...methodBody(menuHandler, 'getMenuItemsList').matchAll(
            /userCan\(\[([^\]]+)\]\)\)\s*\{\s*\$items\[\]\s*=\s*\[\s*'key'\s*=>\s*'(\w+)'/g
        )];
        expect(gates.length).toBe(2);

        gates.forEach(([, keys, menuKey]) => {
            // PermissionManager::userCan() matches any by default, so the route has to as well.
            const meta = router.resolve(`/${menuKey}`).meta;

            expect(meta.permissions).toEqual(keys.match(/'(\w+)'/g).map(key => key.slice(1, -1)));
            expect(meta.match_type).toBe('any');
        });
    });

    it('only asks for permissions PermissionManager can grant', () => {
        allRoutes.filter(route => route.meta?.permissions).forEach(route => {
            route.meta.permissions.forEach(key => expect(grantable, route.path).toContain(key));
        });
    });

    it('closes every edit page to read-only members', () => {
        allRoutes.filter(route => route.name?.endsWith('_edit')).forEach(route => {
            expect(route.meta.permissions.every(key => key.startsWith('write_')), route.name).toBe(true);
            expect(route.meta.match_type).toBeUndefined();
        });
    });

    it('closes the create calendar page to read-only members', () => {
        expect(router.resolve('/calendars/new').meta.permissions).toEqual(['write_calendars']);
    });

    it('closes the create event page to read-only members', () => {
        expect(router.resolve('/events/new').meta.permissions).toEqual(['write_events']);
    });

    it('keeps the Team tab behind manage_all_data alone', () => {
        expect(router.resolve('/settings/team').meta.permissions).toEqual(['manage_all_data']);
    });

    it('sends an unknown path to the not-found page', () => {
        expect(router.resolve('/no-such-page').name).toBe('notfound');
    });
});
