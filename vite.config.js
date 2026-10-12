import fs from 'fs';
import path from 'path';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import liveReload from 'vite-plugin-live-reload';
import AutoImport from 'unplugin-auto-import/vite';
import Components from 'unplugin-vue-components/vite';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers';

const pluginFolder = path.basename(__dirname);
const devPort = process.env.PORT ? parseInt(process.env.PORT) : 8880;

// The resolver injects per-component Element Plus CSS that Vite's startup scan can't see, so the
// first page using a new component forced a re-bundle and a full page reload. Pre-bundle them all.
const elementPlusComponents = path.resolve(__dirname, 'node_modules/element-plus/es/components');
const elementPlusStyles = fs.readdirSync(elementPlusComponents)
    .filter(name => fs.existsSync(`${elementPlusComponents}/${name}/style/css.mjs`))
    .map(name => `element-plus/es/components/${name}/style/css`);

export default defineConfig(({ mode }) => {
    const isDev = mode === 'development';

    return {
        base: `/wp-content/plugins/${pluginFolder}/assets/`,
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
        // warn and error stay in production so real failures remain visible.
        esbuild: isDev ? {} : { pure: ['console.log', 'console.info', 'console.debug'] },
        build: {
            manifest: true,
            outDir: 'assets',
            assetsDir: '',
            emptyOutDir: true,
            rollupOptions: {
                input: [
                    'resources/admin/app.js',
                    'resources/admin/start.js',
                    'resources/scss/admin.scss',
                    // FluentCommunity portal: the route/card loader and its stylesheet.
                    'resources/fcom-portal/fcom-portal.js',
                    'resources/fcom-portal/fcom-portal.scss',
                    // Public landing pages under the portal slug.
                    'resources/portal/portal.scss'
                ],
                output: {
                    chunkFileNames: '[name].js',
                    entryFileNames: '[name].js',
                    assetFileNames: 'admin/[name].[ext]'
                }
            }
        },
        resolve: {
            alias: {
                vue: 'vue/dist/vue.esm-bundler.js',
                '@': path.resolve(__dirname, 'resources/admin')
            },
            extensions: ['.js', '.vue', '.json']
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
