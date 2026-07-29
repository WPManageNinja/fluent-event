<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\App\App;
use FluentEvent\App\Utils\Enqueuer\Enqueue;

class AdminMenuHandler
{
    /**
     * $app Application instance
     * @var \FluentEvent\Framework\Foundation\Application
     */
    protected $app;

    /**
     * $slug Plugin slug defined in config/app
     * @var string
     */
    protected $slug;

    /**
     * $baseUrl Plugin base url
     * @var string
     */
    protected $baseUrl;
    
    /**
     * $app Config instance
     * @var \FluentEvent\Framework\Foundation\Config
     */
    protected $config;

    /**
     * $position Menu Position
     * @var int|float
     */
    protected $position = 6;

    /**
     * Construct the instance
     */
    public function __construct()
    {
        $this->app = App::make();
        $this->config = $this->app->config;
        $this->slug = $this->config->get('app.slug');
        $this->baseUrl = $this->app->applyFilters(
            'fluent_connector_base_url',
            admin_url('admin.php?page=' . $this->slug . '#/')
        );
    }

    /**
     * Add Custom Menu
     * 
     * @return null
     */
    public function add()
    {
        add_menu_page(
            __('Fluent Event', 'fluent-event'),
            __('Fluent Event', 'fluent-event'),
            'manage_options',
            $this->slug,
            [$this, 'render'],
            $this->getMenuIcon(),
            $this->position
        );

        $this->addSubmenu(__('Dashboard', 'fluent-event'), '');

        $this->addSubmenu(__('Add New Event', 'fluent-event'), '#/events/new');
        $this->addSubmenu(__('Calendars', 'fluent-event'), '#/calendars');
        $this->addSubmenu(__('Events', 'fluent-event'), '#/events');
        $this->addSubmenu(__('Settings', 'fluent-event'), '#/settings');
    }

    /**
     * Register one submenu entry against the plugin's single admin page.
     *
     * @param  string $label       Translated menu label.
     * @param  string $route       Hash route appended to the page slug, or ''
     *                             for the parent page itself.
     * @param  string $capability  Capability required to see this entry.
     *                             WordPress filters each submenu on its own
     *                             capability, so entries can differ.
     *
     * @return null
     */
    protected function addSubmenu($label, $route = '', $capability = 'manage_options')
    {
        add_submenu_page(
            $this->slug,
            $label,
            $label,
            $capability,
            $this->slug . $route,
            [$this, 'render']
        );
    }

    /**
     * Render the menu page
     * 
     * @return null
     */
    public function render()
    {
        $this->enqueueAssets($this->slug);

        $this->app->view->render('admin.menu', [
            'slug' => $this->slug,
        ]);
    }

    /**
     * Enqueue all the scripts and styles
     * 
     * @param  string $slug
     * 
     * @return null
     */
    public function enqueueAssets($slug)
    {
        $handle = $slug . '_admin_app';

        $this->triggerAdminBootingHooks();

        $this->enqueueAdminStyles($handle);

        $this->enqueueAdminScripts($handle);

        $this->triggerAdminBootedHooks();

        $this->enqueueStartScript($handle);

        $this->localizeScript($slug, 'admin');
    }

    /**
     * Trigger actions before enqueuing admin assets.
     * 
     * @return null
     */
    protected function triggerAdminBootingHooks()
    {
        $this->app->doCustomAction('_admin_booting');
    }

    /**
     * Enqueue admin CSS styles.
     *
     * @param string $handle Script/style handle.
     * 
     * @return null
     */
    protected function enqueueAdminStyles($handle)
    {
        Enqueue::style($handle, 'admin/admin.css');
    }

    /**
     * Trigger actions after enqueuing main admin app assets.
     *
     * @return null
     */
    protected function triggerAdminBootedHooks()
    {
        $this->app->doCustomAction('_admin_booted');
    }

    /**
     * Enqueue the main admin JavaScript application.
     *
     * @param string $handle Script handle.
     */
    protected function enqueueAdminScripts($handle)
    {
        Enqueue::script(
            $handle,
            'admin/app.js',
            [],
            '1.0',
            true
        );
    }

    /**
     * Enqueue the admin startup script that boots the app.
     *
     * @param string $handle Script handle prefix.
     *
     * @return null
     */
    protected function enqueueStartScript($handle)
    {
        Enqueue::script(
            $handle . '_start',
            'admin/start.js',
            ['wp-api-fetch', $handle],
            '1.0',
            true
        );
    }

    /**
     * Push/Localize the JavaScript variables
     *
     * to the browser using wp_localize_script.
     *
     * @param  string $slug
     * @param  string $context  i18n context name (e.g. 'admin', 'public')
     *
     * @return null
     */
    protected function localizeScript($slug, $context = 'admin')
    {
        $authUser = get_user_by('ID', get_current_user_id());

        $theme = null;
        if ($themes = $this->config->get('theme.themes')) {
            $theme = $themes[$this->config->get('theme.default')] ?? null;
        }

        wp_localize_script($slug . '_admin_app', 'fluentFrameworkAdmin', [
            'env'           => $this->app->env(),
            'slug'          => $slug,
            'theme'         => $theme,
            'user_locale'   => get_locale(),
            'brand_logo'    => $this->getMenuIcon(),
            'nonce'         => wp_create_nonce($slug),
            'asset_url'     => $this->app['url.assets'],
            'rest'          => $r = $this->getRestInfo(),
            'endpoints'     => $this->getRestEndpoinds($r),
            'baseUrl'       => $this->baseUrl,
            'routes'        => $this->getAdminRoutes(),
            'name'          => $this->config->get('app.name'),
            'hook_prefix'   => $this->config->get('app.hook_prefix'),
            'logoUrl'       => Enqueue::getStaticFilePath('images/logo.png'),
            'me'            => [
                'id'        => $authUser->ID ?? null,
                'email'     => $authUser->user_email ?? null,
                'full_name' => $authUser->display_name ?? null,
                'is_admin'  => current_user_can('administrator'),
            ],
            'i18n'          => $this->getTranslationLabels($context),
        ]);
    }

    /**
     * Get and map menu items for main nav.
     *
     * @return \FluentEvent\App\Utils\Vue\Router
     */
    protected function getAdminRoutes()
    {
        return $this->getMenuItems($this->baseUrl);
    }

    /**
     * Get and map menu items for main nav.
     *
     * @param  string $baseUrl
     * @return \FluentEvent\App\Utils\Vue\Router
     */
    protected function getMenuItems($baseUrl)
    {
        return $this->registerRoutesAndMenu();
    }

    /**
     * Register frontend routes and menu items.
     * @return \FluentEvent\App\Utils\Vue\Router
     */
    protected function registerRoutesAndMenu()
    {
        $app = $this->app;
        
        $router = (function() {
            $ns = $this->app->__namespace__;
            
            $class = "$ns\App\Utils\Vue\Router";

            return new $class;
        })();

        require($this->app['path.http'] . '/Routes/vue.php');

        return $router;
    }

    /**
     * Gether rest info/settings for http client.
     * 
     * @return array
     */
    protected function getRestInfo()
    {
        $ns = $this->app->config->get('app.rest_namespace');
        $ver = $this->app->config->get('app.rest_version');

        return [
            'base_url'  => $this->getBaseRestUrl(),
            'url'       => $this->getFullRestUrl($ns, $ver),
            'nonce'     => wp_create_nonce('wp_rest'),
            'namespace' => $ns,
            'version'   => $ver
        ];
    }

    /**
     * Get base rest url by examining the permalink.
     * 
     * @see https://wordpress.stackexchange.com/questions/273144/can-i-use-rest-api-on-plain-permalink-format
     * 
     * @return string
     */
    protected function getBaseRestUrl()
    {
        if (get_option('permalink_structure')) {
            return esc_url_raw(rest_url());
        }

        return esc_url_raw(
            rtrim(get_site_url(), '/') . "/?rest_route=/"
        );
    }

    /**
     * Get the full rest url by examining the permalink
     * (full means, including the namespace/version).
     * 
     * @param $ns Rest Namespace
     * @param $ver Rest Version
     * @see https://wordpress.stackexchange.com/questions/273144/can-i-use-rest-api-on-plain-permalink-format
     * 
     * @return string
     */
    protected function getFullRestUrl($ns, $ver)
    {
        if (get_option('permalink_structure')) {
            return esc_url_raw(rest_url($ns . '/' . $ver));
        }

        return esc_url_raw(
            rtrim(get_site_url(), '/') . "/?rest_route=/{$ns}/{$ver}"
        );
    }

    /**
     * Retrieve rest endpoints for client.
     * 
     * @param  array $r Rest Info
     * 
     * @return array
     */
    protected function getRestEndpoinds($r)
    {
        $url = rest_url($r['namespace'] . '/' . $r['version'] . '/__endpoints');

        $result = wp_remote_get($url, [
            'timeout'     => 15,
            'redirection' => 2,
            'sslverify'   => false,
            'cookies'     => $_COOKIE,
            'user-agent'  => "wpfluent.{$this->slug}.__endpoints",
            'headers'     => [
                'X-Wp-Nonce' => $r['nonce']
            ],
        ]);

        $code = wp_remote_retrieve_response_code($result);
        $body = wp_remote_retrieve_body($result);

        if (is_wp_error($result)) {
            return [];
        }

        if ($code === 200) {
            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }
            return $data;
        }

        return [];
    }

    /**
     * Get the default icon for custom menu
     * added by the add_menu in the WP menubar.
     * 
     * @return string
     */
    protected function getMenuIcon()
    {
        if (str_starts_with($this->app->env(), 'dev')) {
            $path = 'resources/images/favicon.png';
        } else {
            $path = 'assets/images/favicon.png';
        }

        $file = plugin_dir_path($this->app->__pluginfile__) . $path;
        
        if (file_exists($file)) {
            return plugin_dir_url($this->app->__pluginfile__) . $path;
        }

        return 'dashicons-wordpress-alt';

    }

    /**
     * Get translation labels for the Vue SPA frontend.
     *
     * Reads from config/i18n.php which contains:
     *   'manifest'     => ['admin' => 'resources/admin', ...],
     *   'translations'  => ['admin' => [...], 'public' => [...]]
     *
     * @param  string $context  i18n context name (e.g. 'admin', 'public')
     *
     * @return array
     */
    protected function getTranslationLabels($context = 'admin')
    {
        $i18n = $this->config->get('i18n');

        $labels = $i18n['translations'][$context] ?? ($i18n ?: []);

        return $this->app->applyCustomFilters(
            'i18n_labels', $labels ?: [], $context
        );
    }

    /**
     * Makes the class invokable.
     * 
     * @return null
     */
    public function __invoke()
    {
        $this->add();
    }
}
