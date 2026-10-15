import { describe, it, expect } from 'vitest';
import viteConfig from '../../vite.config.js';

describe('vite build config', () => {
    // Vite bakes `base` into the preload helper every lazy route goes through. An absolute
    // /wp-content/plugins/... path 404s the route's CSS on any other install layout.
    it('uses a relative base so lazy chunks resolve from the loaded script, not the site root', () => {
        const { base } = viteConfig({ mode: 'production', command: 'build' });

        expect(base).not.toMatch(/^(\/|[a-z]+:)/i);
    });
});
