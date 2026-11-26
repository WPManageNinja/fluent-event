import { describe, it, expect } from 'vitest';
import pkg from '../../package.json';
import lock from '../../package-lock.json';

// .claude/rules/deps-build.md: a range lets a lockfile refresh ship a new version into the bundle unreviewed.
describe('runtime dependencies', () => {
    it.each(Object.entries(pkg.dependencies))('pins %s to the locked version', (name, version) => {
        expect(version).toMatch(/^\d+\.\d+\.\d+$/);
        expect(version).toBe(lock.packages[`node_modules/${name}`].version);
        expect(lock.packages[''].dependencies[name]).toBe(version);
    });
});
