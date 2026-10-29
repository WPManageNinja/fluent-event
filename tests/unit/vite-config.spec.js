import { describe, it, expect } from 'vitest';
import viteConfig from '../../vite.config.js';

describe('vite build config', () => {
    // Vite bakes `base` into the preload helper every lazy route goes through. An absolute
    // /wp-content/plugins/... path 404s the route's CSS on any other install layout.
    it('uses a relative base so lazy chunks resolve from the loaded script, not the site root', () => {
        const { base } = viteConfig({ mode: 'production', command: 'build' });

        expect(base).not.toMatch(/^(\/|[a-z]+:)/i);
    });

    // Chunks import each other by relative URL, without the entries' ?ver=, so only a content hash makes
    // a long-cached copy miss after an update. Entries keep stable names: PHP enqueues them with ?ver=.
    it('hashes chunk and asset names and keeps entry names stable', () => {
        const { output } = viteConfig({ mode: 'production', command: 'build' }).build.rollupOptions;

        expect(output.chunkFileNames).toContain('[hash]');
        expect(output.assetFileNames).toContain('[hash]');
        expect(output.entryFileNames({ name: 'app' })).not.toContain('[hash]');
        expect(output.entryFileNames({ name: 'start' })).not.toContain('[hash]');
    });

    // fcom loads it with fcom's own ?ver=, which does not change when only FluentEvent updates.
    it('hashes the fcom portal entry', () => {
        const { output } = viteConfig({ mode: 'production', command: 'build' }).build.rollupOptions;

        expect(output.entryFileNames({ name: 'fcom-portal' })).toContain('[hash]');
    });
});
