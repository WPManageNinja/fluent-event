import { describe, it, expect, afterEach } from 'vitest';
import viteConfig from '../../vite.config.js';
import { APPS } from '../../resources/vite/apps.mjs';

// npm run build builds one app per run (resources/vite/build.mjs), naming it in VITE_APP_NAME.
function buildConfig(appName = 'admin') {
    process.env.VITE_APP_NAME = appName;
    return viteConfig({ mode: 'production', command: 'build' });
}

describe('vite build config', () => {
    afterEach(() => {
        delete process.env.VITE_APP_NAME;
    });

    // Vite bakes `base` into the preload helper every lazy route goes through. An absolute
    // /wp-content/plugins/... path 404s the route's chunks on any other install layout.
    it('uses a relative base so lazy chunks resolve from the loaded script, not the site root', () => {
        const { base } = buildConfig();

        expect(base).not.toMatch(/^(\/|[a-z]+:)/i);
    });

    // Chunks import each other by relative URL, without the entries' ?ver=, so only a content hash makes
    // a long-cached copy miss after an update. Entries keep stable names: PHP enqueues them with ?ver=.
    it('hashes chunk and asset names and keeps entry names stable', () => {
        const { output } = buildConfig().build.rollupOptions;

        expect(output.chunkFileNames).toContain('[hash]');
        expect(output.assetFileNames({ name: 'admin.css' })).toContain('[hash]');
        expect(output.entryFileNames({ name: 'app' })).not.toContain('[hash]');
        expect(output.entryFileNames({ name: 'start' })).not.toContain('[hash]');
    });

    // fcom loads it with fcom's own ?ver=, which does not change when only FluentEvent updates.
    it('hashes the fcom portal entry', () => {
        const { output } = buildConfig('fcom-portal').build.rollupOptions;

        expect(output.entryFileNames({ name: 'fcom-portal' })).toContain('[hash]');
    });

    // A bare `vite build` chunks every app together and splits CSS into files the browser loads at
    // runtime, which the RTL step cannot mirror.
    it('refuses a build that names no app', () => {
        expect(() => viteConfig({ mode: 'production', command: 'build' })).toThrow(/npm run build/);
    });

    it('serves every input to the dev server', () => {
        const { input } = viteConfig({ mode: 'development', command: 'serve' }).build.rollupOptions;

        expect(input).toEqual(Object.values(APPS).flat());
    });

    // One stylesheet per JS app, enqueued by PHP and mirrored by the RTL step; the SCSS entries keep
    // one file each. The fcom portal shares its page with fcom, so its CSS stays in its lazy chunks.
    it('keeps each JS app in one stylesheet, except the fcom portal', () => {
        for (const name of Object.keys(APPS)) {
            expect(buildConfig(name).build.cssCodeSplit).toBe(name === 'styles' || name === 'fcom-portal');
        }
    });

    it('names a JS app stylesheet after its app, with a hash', () => {
        const { output } = buildConfig('admin').build.rollupOptions;

        expect(output.assetFileNames({ name: 'style.css' })).toBe('admin/admin-app-[hash][extname]');
    });

    // The browser fetches the portal's lazy CSS itself, so the chunk has to pick the RTL twin.
    it('lets the fcom portal chunks pick the RTL twin of their lazy CSS', () => {
        const { renderBuiltUrl } = buildConfig('fcom-portal').experimental;
        const url = renderBuiltUrl('admin/EventsPage-abc.css', { hostId: 'fcom-portal-abc.js', hostType: 'js' });

        expect(url.runtime).toContain('fluentEventFcomPortal.is_rtl');
        expect(url.runtime).toContain('"./admin/EventsPage-abc.rtl.css"');
        expect(url.runtime).toContain('"./admin/EventsPage-abc.css"');
        expect(renderBuiltUrl('logo-abc.png', { hostId: 'fcom-portal-abc.js', hostType: 'js' })).toBeUndefined();
        expect(renderBuiltUrl('font-abc.woff2', { hostId: 'admin/x.css', hostType: 'css' })).toBeUndefined();
        expect(buildConfig('admin').experimental.renderBuiltUrl).toBeUndefined();
    });

    // build.mjs merges the per-run manifests; one shared name would let each run overwrite the last.
    it('writes a manifest per app', () => {
        expect(buildConfig('portal').build.manifest).toBe('manifest-portal.json');
    });
});
