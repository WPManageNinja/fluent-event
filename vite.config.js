import fs from 'fs';
import path from 'path';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import liveReload from 'vite-plugin-live-reload';
import AutoImport from 'unplugin-auto-import/vite';
import Components from 'unplugin-vue-components/vite';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers';
// One app per build run (npm run build); see the file for why.
import { APPS } from './resources/vite/apps.mjs';

const devPort = process.env.PORT ? parseInt(process.env.PORT) : 8890;

// The resolver injects per-component Element Plus CSS that Vite's startup scan can't see, so the
// first page using a new component forced a re-bundle and a full page reload. Pre-bundle them all.
const elementPlusComponents = path.resolve(__dirname, 'node_modules/element-plus/es/components');
const elementPlusStyles = fs.readdirSync(elementPlusComponents)
    .filter(name => fs.existsSync(`${elementPlusComponents}/${name}/style/css.mjs`))
    .map(name => `element-plus/es/components/${name}/style/css`);

// The <app>-core chunk: every module an entry reaches through static imports, except the entries.
function coreChunk(appName) {
    return (id, { getModuleInfo }) => {
        const info = getModuleInfo(id);
        if (!info || info.isEntry) {
            return undefined;
        }

        const seen = new Set([id]);
        const queue = [...info.importers];
        while (queue.length) {
            const importer = queue.shift();
            if (seen.has(importer)) {
                continue;
            }
            seen.add(importer);
            const importerInfo = getModuleInfo(importer);
            if (importerInfo?.isEntry) {
                return `${appName}-core`;
            }
            queue.push(...(importerInfo?.importers || []));
        }

        return undefined;
    };
}

export default defineConfig(({ mode, command }) => {
    const isDev = mode === 'development';
    const appName = process.env.VITE_APP_NAME;

    // A bare `vite build` would chunk every app together and split CSS into runtime-loaded files the
    // RTL step can't serve. Only `npm run build` produces a correct assets/.
    if (command === 'build' && !appName) {
        throw new Error('Run `npm run build`, not `vite build` directly.');
    }
    if (appName && !APPS[appName]) {
        throw new Error(`Unknown VITE_APP_NAME "${appName}".`);
    }

    return {
        // Relative: lazy chunks and their CSS resolve from the loaded script's URL. An absolute path
        // breaks WordPress in a subdirectory, a custom plugins URL, or a checkout under another name.
        base: './',
        plugins: [
            vue(),
            liveReload([`${__dirname}/**/*.php`]),
            viteStaticCopy({
                targets: [
                    { src: 'resources/images/*', dest: 'images' },
                    { src: 'resources/public/lib', dest: 'public/' }
                ]
            }),
            AutoImport({ resolvers: [ElementPlusResolver()] }),
            Components({ resolvers: [ElementPlusResolver()], directives: false })
        ],
        define: { __DEV__: isDev },
        // The blocks' JSX builds on WordPress's own React, as the Fluent suite's blocks do.
        // warn and error stay in production so real failures remain visible.
        esbuild: {
            jsxFactory: 'wp.element.createElement',
            jsxFragment: 'wp.element.Fragment',
            ...(isDev ? {} : { pure: ['console.log', 'console.info', 'console.debug'] }),
        },
        build: {
            // One manifest per run; build.js merges them into manifest.json.
            manifest: appName ? `manifest-${appName}.json` : true,
            outDir: 'assets',
            assetsDir: '',
            // build.js empties assets/ on the first run only, so later runs add to it.
            emptyOutDir: process.env.VITE_EMPTY_OUT_DIR === 'yes',
            cssCodeSplit: !appName || appName === 'styles',
            rollupOptions: {
                input: appName ? APPS[appName] : Object.values(APPS).flat(),
                // Chunks import each other without the entries' ?ver=, so their names carry a content hash.
                output: {
                    // Built alone, an app's entry would hold the modules it shares with its lazy chunks,
                    // and those chunks would import the entry by a URL without the ?ver= it was loaded
                    // with (PHP's, or fcom's for the portal entry): the browser runs the entry twice.
                    // So everything an entry imports statically goes to one hashed <app>-core chunk.
                    // build.mjs fails if any chunk still imports an entry.
                    manualChunks: appName && appName !== 'styles' ? coreChunk(appName) : undefined,
                    chunkFileNames: '[name]-[hash].js',
                    // fcom stamps the add-on scripts it loads with its own ?ver=, so the portal entry's name carries the hash.
                    entryFileNames: (chunk) => (chunk.name === 'fcom-portal' ? '[name]-[hash].js' : '[name].js'),
                    // An app's single stylesheet comes out named "style"; name it after its app instead.
                    assetFileNames: (asset) => (appName && appName !== 'styles' && asset.name === 'style.css'
                        ? `admin/${appName}-app-[hash][extname]`
                        : 'admin/[name]-[hash][extname]')
                }
            }
        },
        resolve: {
            alias: {
                vue: 'vue/dist/vue.esm-bundler.js',
                '@': path.resolve(__dirname, 'resources/admin')
            },
            extensions: ['.js', '.jsx', '.vue', '.json']
        },
        optimizeDeps: {
            include: elementPlusStyles
        },
        server: {
            port: devPort,
            strictPort: true,
            cors: {
                origin: '*',
                methods: ['GET'],
                allowedHeaders: ['Content-Type', 'Authorization'],
            },
            hmr: {
                port: devPort,
                host: 'localhost',
                protocol: 'ws'
            }
        }
    };
});
