import { createApp, reactive } from 'vue';
import mixins from '@/mixins';
import config from './config';
import Rest from '@/utils/http/Rest';
import ResponseProxyItr from '@/utils/http/ResponseProxyItr';
import { ElNotification } from 'element-plus';

export default class Application {

    static create(ApplicationComponent, plugins = []) {
        return new this().createVueApp(ApplicationComponent, plugins);
    }

    createVueApp(ApplicationComponent, plugins = []) {
        const app = createApp(ApplicationComponent, {
            baseUrl: fluentEventAdmin.baseUrl || '#',
            logoUrl: fluentEventAdmin.logoUrl || '',
        });

        this.registerMixins(app);
        this.registerGlobalProperties(app);
        Rest.setInstance(app);
        this.registerPlugins(app, plugins);
        this.registerGlobalErrorHandlers(app);

        return this.instance(app);
    }

    registerMixins(app) {
        mixins.forEach(mixin => app.mixin(mixin));
    }

    registerGlobalProperties(app) {
        app.config.globalProperties.appVars = fluentEventAdmin;
        Object.assign(config, fluentEventAdmin);

        app.config.globalProperties.config = config;

        const i18n = fluentEventAdmin.i18n || {};

        /**
         * Translate a string, then fill its `%s` / `%d` placeholders in order —
         * `$t('%s: %s', group, value)`. Same call shape as the rest of the suite.
         * Positional forms (`%1$s`) are honoured too, so a translation can reorder
         * arguments when the target language needs a different word order.
         */
        app.config.globalProperties.$t = (key, ...args) => {
            const text = i18n[key] || key;

            if (!args.length) {
                return text;
            }

            let index = 0;

            return text.replace(/%(?:(\d+)\$)?[sd]/g, (match, position) => {
                const arg = position ? args[position - 1] : args[index++];

                return arg === undefined ? '' : arg;
            });
        };

        /** BCP-47 locale derived from WordPress's `user_locale` (e.g. `en-US`) for Intl.NumberFormat. */
        const locale = (fluentEventAdmin.user_locale || 'en_US').replace('_', '-');
        app.config.globalProperties.$n = (value, options) => {
            if (typeof value !== 'number' || !isFinite(value)) return String(value);
            try {
                return new Intl.NumberFormat(locale, options).format(value);
            } catch {
                return String(value);
            }
        };
    }

    registerPlugins(app, plugins = []) {
        plugins.forEach(plugin => {
            if (Array.isArray(plugin)) {
                // plugin[0] = plugin object,
                // plugin[1] = options
                app.use(plugin[0], plugin[1]);
            } else {
                app.use(plugin);
            }
        });

        this.registerValidationErrorHandler(app);
    }

    registerValidationErrorHandler(app) {
        app.use({
            install(app) {
                const validationErrors = reactive({});
                const $gp = app.config.globalProperties;

                $gp.$validationErrors = validationErrors;

                $gp.$clearErrors = () => {
                    Object.keys(validationErrors).forEach(key => delete validationErrors[key]);
                };

                $gp.$handleValidationError = function(err) {
                    const errors = err.errors || err.all?.() || {};
                    this.$clearErrors();
                    Object.entries(errors).forEach(([key, val]) => {
                        validationErrors[key] = val;
                    });
                };
            }
        });
    }

    registerGlobalErrorHandlers(app) {
        // Vue error handler
        app.config.errorHandler = (err, vm, info) => {
            if (err.status === 422 && vm?.$handleValidationError) {
                return vm.$handleValidationError(err);
            }

            console.groupCollapsed(
                `%c[ERROR] Fluent - Vue Global Error\n%c${err?.message || err}`,
                "background: darkred; color: white; font-weight: bold; padding: 2px 4px; border-radius: 3px;",
                "color: darkorange;"
            );
            if (vm) console.log("%cComponent:", "color: royalblue;", vm);
            if (info) console.log("%cInfo:", "color: darkgreen;", info);
            console.error(err?.stack);
            console.groupEnd();
        };

        // Unhandled promise rejections
        let rejectionTimer;
        window.addEventListener('unhandledrejection', (event) => {
            const err = event.reason;

            if (!(err instanceof ResponseProxyItr)) return;

            event.preventDefault();

            clearTimeout(rejectionTimer);
            rejectionTimer = setTimeout(() => {
                if (err?.status === 422) {
                    app.config.globalProperties.$handleValidationError(err);
                } else {
                    ElNotification({
                        title: app.config.globalProperties.$t('Error'),
                        message: String(err?.message || err),
                        type: 'warning',
                    });
                }
            }, 50);
        });
    }

    instance(vue) {
        return {
            vue,
            addFilter: wp.hooks.addFilter.bind(wp.hooks),
            applyFilters: wp.hooks.applyFilters.bind(wp.hooks),
            addAction: wp.hooks.addAction.bind(wp.hooks),
            doAction: wp.hooks.doAction.bind(wp.hooks),
        };
    }
}
