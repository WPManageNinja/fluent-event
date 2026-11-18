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
        $this->baseUrl = apply_filters(
            'fluent_event/admin_base_url',
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
            'block_inserter_image' => Enqueue::getStaticFilePath('images/block-inserter.webp'),
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
     * The FluentEvent ticket-date mark in one colour, so WordPress tints it with
     * the admin colour scheme, as it does the other Fluent plugins' menu icons.
     *
     * @return string
     */
    protected function getSidebarIcon()
    {
        return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="50 50 140 140"><path fill="white" fill-rule="evenodd" d="M50 76A26 26 0 0 1 76 50H164A26 26 0 0 1 190 76V84H50ZM50 96H190V128A12 12 0 0 0 190 152V164A26 26 0 0 1 164 190H76A26 26 0 0 1 50 164V152A12 12 0 0 0 50 128ZM84.65 137.97C84.65 133.55 86.41 129.31 89.53 126.18C92.66 123.06 96.9 121.3 101.32 121.3H155.62C155.62 125.72 153.86 129.96 150.73 133.09C147.61 136.21 143.37 137.97 138.95 137.97H84.65ZM84.65 162.7C84.65 160.51 85.08 158.34 85.92 156.32C86.76 154.3 87.99 152.46 89.53 150.91C91.08 149.37 92.92 148.14 94.94 147.3C96.96 146.46 99.13 146.03 101.32 146.03H131.83C131.83 150.45 130.07 154.69 126.95 157.82C123.82 160.94 119.58 162.7 115.16 162.7H84.65Z"/></svg>');
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
