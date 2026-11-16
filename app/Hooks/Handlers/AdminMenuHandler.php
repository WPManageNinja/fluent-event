<?php

namespace FluentEvent\App\Hooks\Handlers;

use FluentEvent\App\App;
use FluentEvent\App\Models\Calendar;
use FluentEvent\App\Models\Event;
use FluentEvent\App\Services\Settings;
use FluentEvent\App\Utils\DateTimeHelper;
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
            __('FluentEvent', 'fluent-event'),
            __('FluentEvent', 'fluent-event'),
            'manage_options',
            $this->slug,
            [$this, 'render'],
            $this->getSidebarIcon(),
            $this->position
        );

        $this->addSubmenu(__('Dashboard', 'fluent-event'), '');
        $this->addSubmenu(__('Add New Event', 'fluent-event'), 'events/new');
        $this->addSubmenu(__('Calendars', 'fluent-event'), 'calendars');
        $this->addSubmenu(__('Events', 'fluent-event'), 'events');
        $this->addSubmenu(__('Settings', 'fluent-event'), 'settings');
    }

    /**
     * Register one submenu entry against the plugin's single admin page.
     *
     * @param  string $label       Translated menu label.
     * @param  string $route       SPA route appended to the base URL, or '' for
     *                             the parent page itself.
     *
     * @return null
     */
    protected function addSubmenu($label, $route)
    {
        add_submenu_page(
            $this->slug,
            $label,
            $label,
            'manage_options',
            $route ? esc_url($this->baseUrl . $route) : $this->slug,
            $route ? '' : [$this, 'render']
        );
    }

    public function removeAdminNotices()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'toplevel_page_' . $this->slug) {
            return;
        }
        remove_all_actions('network_admin_notices');
        remove_all_actions('user_admin_notices');
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
    }

    /**
     * Set the dark class in <head> before paint, so dark mode has no light flash.
     *
     * @return null
     */
    protected function enqueueThemeClass()
    {
        $script = <<<'JS'
(function(){var d=document.documentElement,t='';try{t=localStorage.getItem('fluent_theme_mode')||'';}catch(e){}
var p=t.split(':'),m=(p[0]==='light'||p[0]==='dark')?p[0]:'system',r=p[1]||(m!=='system'?m:(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'));
d.setAttribute('data-fe-theme',m);if(r==='dark'){d.classList.add('fluent_theme_dark','dark');}})();
JS;

        wp_register_script($this->slug . '_theme_class', false, [], FLUENT_EVENT_PLUGIN_VERSION, false);
        wp_enqueue_script($this->slug . '_theme_class');
        wp_add_inline_script($this->slug . '_theme_class', $script);
    }

    /**
     * Enqueue the admin page assets before the admin header prints, so the
     * theme class and stylesheet land in <head> and the PHP topbar paints
     * already styled.
     *
     * @param string $hookSuffix The current admin page.
     *
     * @return null
     */
    public function enqueueAdminPageAssets($hookSuffix)
    {
        if ($hookSuffix !== 'toplevel_page_' . $this->slug) {
            return;
        }

        $this->enqueueThemeClass();
        $this->enqueueAssets($this->slug);
    }

    /**
     * Render the menu page
     *
     * @return null
     */
    public function render()
    {
        $this->app->view->render('admin.menu', [
            'slug'           => $this->slug,
            'baseUrl'        => $this->baseUrl,
            'name'           => $this->config->get('app.name'),
            'logo'           => Enqueue::getStaticFilePath('images/logo.svg'),
            'darkLogo'       => Enqueue::getStaticFilePath('images/logo-dark.svg'),
            'menuItems'      => $this->getMenuItemsList(),
            'rightMenuItems' => $this->getRightMenuItemsList(),
        ]);
    }

    /**
     * Topbar items rendered in the middle of the navbar.
     *
     * @return array<int, array<string, string>>
     */
    protected function getMenuItemsList()
    {
        return [
            [
                'key'       => 'dashboard',
                'label'     => __('Dashboard', 'fluent-event'),
                'permalink' => $this->baseUrl,
            ],
            [
                'key'       => 'calendars',
                'label'     => __('Calendars', 'fluent-event'),
                'permalink' => $this->baseUrl . 'calendars',
            ],
            [
                'key'       => 'events',
                'label'     => __('Events', 'fluent-event'),
                'permalink' => $this->baseUrl . 'events',
            ],
        ];
    }

    /**
     * Topbar items rendered on the right, before the theme toggle.
     *
     * @return array<int, array<string, string>>
     */
    protected function getRightMenuItemsList()
    {
        $children = [
            [
                'key'       => 'settings_general',
                'label'     => __('General', 'fluent-event'),
                'permalink' => $this->baseUrl . 'settings/general',
            ],
            [
                'key'       => 'settings_email_notifications',
                'label'     => __('Email Notifications', 'fluent-event'),
                'permalink' => $this->baseUrl . 'settings/email-notifications',
            ],
        ];

        return [
            [
                'key'       => 'settings',
                'label'     => __('Settings', 'fluent-event'),
                'permalink' => $this->baseUrl . 'settings',
                'children'  => $children,
            ],
        ];
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

        $this->enqueueRichTextEditor();

        $this->enqueueMediaLibrary();

        $this->localizeScript($slug, 'admin');
    }

    protected function enqueueMediaLibrary()
    {
        wp_enqueue_media();
    }

    protected function enqueueRichTextEditor()
    {
        if (!function_exists('wp_enqueue_editor')) {
            return;
        }

        add_filter('user_can_richedit', '__return_true');

        wp_enqueue_editor();
        add_thickbox();
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
        Enqueue::style($handle, 'scss/admin.scss', [], FLUENT_EVENT_PLUGIN_VERSION);
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
            FLUENT_EVENT_PLUGIN_VERSION,
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
            FLUENT_EVENT_PLUGIN_VERSION,
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

        wp_localize_script($slug . '_admin_app', 'fluentEventAdmin', [
            'env'           => $this->app->env(),
            'slug'          => $slug,
            'user_locale'   => get_locale(),
            'timezone'      => wp_timezone_string(),
            'start_of_week' => (int) get_option('start_of_week', 0),
            'calendar_default_color' => Calendar::DEFAULT_THEME_COLOR,
            'calendar_title_max_length' => Calendar::titleMaxLength(),
            'event_venue_max_length' => Event::venueMaxLength(),
            'general_settings' => Settings::get(),
            'email_notifications' => Settings::get(Settings::EMAIL_NOTIFICATIONS),
            'date_format'      => DateTimeHelper::convertToDayJsFormat(get_option('date_format')),
            'time_format'      => DateTimeHelper::convertToDayJsFormat(get_option('time_format')),
            'brand_logo'    => $this->getMenuIcon(),
            'nonce'         => wp_create_nonce($slug),
            'asset_url'     => $this->app['url.assets'],
            'rest'          => $this->getRestInfo(),
            'baseUrl'       => $this->baseUrl,
            'name'          => $this->config->get('app.name'),
            'hook_prefix'   => $this->config->get('app.hook_prefix'),
            'logoUrl'       => Enqueue::getStaticFilePath('images/logo.svg'),
            'fcom_logo'     => Enqueue::getStaticFilePath('images/fluent-community.svg'),
            'me'            => [
                'id'          => $authUser->ID ?? null,
                'email'       => $authUser->user_email ?? null,
                'full_name'   => $authUser->display_name ?? null,
                'avatar'      => get_avatar_url($authUser->ID ?? 0),
            ],
            'i18n'          => $this->getTranslationLabels($context),
        ]);
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
     * The FluentEvent tile and ticket mark in one colour, so WordPress tints it with
     * the admin colour scheme, as it does the other Fluent plugins' menu icons.
     *
     * @return string
     */
    protected function getSidebarIcon()
    {
        return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 176 176"><path fill="white" fill-rule="evenodd" d="M17.6 0H158.4C168.12 0 176 7.88 176 17.6V158.4C176 168.12 168.12 176 158.4 176H17.6C7.88 176 0 168.12 0 158.4V17.6C0 7.88 7.88 0 17.6 0Z M 40.71 37.66 H 135.28 C 141.35 37.66 147.17 40.07 151.46 44.37 C 155.75 48.66 158.16 54.48 158.16 60.54 V 74.27 C 154.52 74.27 151.03 75.72 148.46 78.29 C 145.88 80.87 144.44 84.36 144.44 88 C 144.44 91.64 145.88 95.13 148.46 97.71 C 151.03 100.28 154.52 101.73 158.16 101.73 V 115.46 C 158.16 121.52 155.75 127.34 151.46 131.63 C 147.17 135.93 141.35 138.34 135.28 138.34 H 40.71 C 34.65 138.34 28.83 135.93 24.54 131.63 C 20.25 127.34 17.83 121.52 17.83 115.46 V 101.73 C 21.48 101.73 24.97 100.28 27.54 97.71 C 30.12 95.13 31.56 91.64 31.56 88 C 31.56 84.36 30.12 80.87 27.54 78.29 C 24.97 75.72 21.48 74.27 17.83 74.27 V 60.54 C 17.83 54.48 20.25 48.66 24.54 44.37 C 28.83 40.07 34.65 37.66 40.71 37.66 Z"/><path fill="white" d="M 47.88 83.42 C 47.88 78.41 49.88 73.6 53.42 70.05 C 56.97 66.5 61.78 64.51 66.8 64.51 H 128.42 C 128.42 69.53 126.43 74.34 122.88 77.88 C 119.33 81.43 114.52 83.42 109.51 83.42 H 47.88 Z M 47.88 111.49 C 47.88 109.01 48.37 106.55 49.32 104.25 C 50.27 101.96 51.67 99.87 53.42 98.12 C 55.18 96.36 57.27 94.97 59.56 94.02 C 61.85 93.07 64.31 92.58 66.8 92.58 H 101.42 C 101.42 97.59 99.43 102.4 95.88 105.95 C 92.34 109.5 87.53 111.49 82.51 111.49 H 47.88 Z"/></svg>');
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
