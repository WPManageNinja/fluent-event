import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import AutoImport from 'unplugin-auto-import/vite';
import Components from 'unplugin-vue-components/vite';
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers';

const rootDir = path.dirname(fileURLToPath(import.meta.url));

// test.env alone does not move the clock: vitest 0.34 runs in worker threads, which only see a copy of
// process.env. Set here, in the main process, the zone is already UTC when the workers start.
process.env.TZ = 'UTC';

// Separate from vite.config.js, which is a build pipeline (entries, static copy, live reload, dev server).
// Only the plugins and aliases that decide how a component resolves its imports are mirrored here.
export default defineConfig({
    plugins: [
        vue(),
        AutoImport({ resolvers: [ElementPlusResolver()], dts: false }),
        Components({ resolvers: [ElementPlusResolver()], directives: false, dts: false })
    ],

    test: {
        globals: true,
        environment: 'happy-dom',
        // fcom-portal and date helpers format through the local zone; pinned so every machine agrees.
        env: { TZ: 'UTC' },
        // A child process, unlike a worker thread, picks up a zone the spec sets at runtime: this one previews in
        // a browser whose clocks change, and must still count on the event's clock.
        poolMatchGlobs: [['**/registration-limit-field.spec.js', 'child_process']],
        setupFiles: ['./tests/setup.js'],
        include: ['tests/unit/**/*.spec.js'],
        exclude: ['**/node_modules/**', 'tests/php/**', 'tests/lint/**'],
        server: {
            deps: {
                // The resolver's per-component style imports pull .css files Node cannot load on its own.
                inline: ['element-plus']
            }
        }
    },

    // A worktree may symlink node_modules; Vite only serves files under an allowed root, so allow its real path too.
    server: { fs: { allow: [rootDir, fs.realpathSync(path.join(rootDir, 'node_modules'))] } },

    resolve: {
        alias: {
            // The full build: fcom-portal components are string templates compiled at runtime.
            vue: 'vue/dist/vue.esm-bundler.js',
            '@': path.resolve(rootDir, 'resources/admin')
        },
        extensions: ['.js', '.vue', '.json']
    }
});
