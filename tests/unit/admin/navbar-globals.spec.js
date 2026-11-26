import { describe, it, expect } from 'vitest';
import fs from 'fs';
import { fileURLToPath } from 'url';

const read = (relative) => fs.readFileSync(fileURLToPath(new URL(relative, import.meta.url)), 'utf8');
const appJs = read('../../../resources/admin/app.js');
const menuPhp = read('../../../app/Views/admin/menu.php');

describe('topbar window globals', () => {
    it('defines only fluentEvent-prefixed globals', () => {
        const globals = [...appJs.matchAll(/^window\.(\w+)\s*=/gm)].map(([, name]) => name);

        expect(globals.length).toBeGreaterThan(0);
        globals.forEach((name) => expect(name).toMatch(/^fluentEvent[A-Z]/));
    });

    it('calls only globals app.js defines from the topbar onclick handlers', () => {
        const calls = [...menuPhp.matchAll(/onclick="(\w+)\(/g)].map(([, name]) => name);

        expect(calls.length).toBeGreaterThan(0);
        calls.forEach((name) => expect(appJs).toMatch(new RegExp(`^window\\.${name}\\s*=`, 'm')));
    });
});
