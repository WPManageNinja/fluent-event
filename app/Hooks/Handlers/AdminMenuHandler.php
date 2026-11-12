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
            $this->getMenuIcon(),
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
     * Render the menu page
     * 
     * @return null
     */
    public function render()
    {
        $this->enqueueAssets($this->slug);

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

        $theme = null;
        if ($themes = $this->config->get('theme.themes')) {
            $theme = $themes[$this->config->get('theme.default')] ?? null;
        }

        wp_localize_script($slug . '_admin_app', 'fluentEventAdmin', [
            'env'           => $this->app->env(),
            'slug'          => $slug,
            'theme'         => $theme,
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
