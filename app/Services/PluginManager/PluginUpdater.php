<?php

namespace FluentEvent\App\Services\PluginManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Self-hosted update checks for FluentEvent.
 *
 * Only the non-WordPress.org build ships this file: `./build.sh --wporg` leaves it
 * out, and WordPress.org then delivers updates itself.
 *
 * FluentEvent is free, so it is served by the addon registry rather than the
 * store: the only identity in a request is the slug, there is no licence key,
 * and the endpoint is public. The registry never compares versions - it always
 * answers with the current release and the comparison happens here.
 *
 * Packages are checked against the SHA-256 the registry publishes alongside the
 * download URL before WordPress is allowed to unzip anything. That catches a
 * package swapped in storage or a download that arrived damaged. It is not a
 * signature: the same response carries both the URL and the hash, so it cannot
 * speak to an API that has itself been taken over. The signed-manifest scheme
 * used by the paid plugins needs release artifacts that only store products
 * have, and the registry has none - so this is the strongest check available
 * here, not a weaker version of one that was on offer.
 */
class PluginUpdater
{
    /**
     * Marks a failed lookup in the cache.
     *
     * A failure has to be remembered as a failure. Caching it as an empty
     * success would read back as "there is no update", which is a different
     * claim than "we could not ask".
     */
    const FETCH_FAILED = 'fetch_failed';

    /**
     * The caching key for version info.
     *
     * @var string
     */
    private $cache_key;

    private $config = [];

    /** @var bool one uncached retry per request, no more */
    private $refetched = false;

    /** @var bool set while the Check Update link runs, so the multisite skip does not swallow it */
    private $forceCheck = false;

    /**
     * FluentEvent's own updater, against the addon registry. FluentEvent is free: no licence
     * key and no product id, so the slug is the whole identity and has to match the plugin
     * directory name. The config lives here, not in boot/app.php, so the WordPress.org build
     * drops every trace of the registry by dropping this one file.
     *
     * @param string $file The main plugin file.
     * @return static
     */
    public static function register($file)
    {
        return new static([
            'slug'              => 'fluent-event',
            'version'           => FLUENT_EVENT_PLUGIN_VERSION,
            'basename'          => plugin_basename($file),
            'api_url'           => 'https://fluentapi.wpmanageninja.com/addons',
            'show_check_update' => true,
            'verify_checksum'   => !(defined('FLUENT_EVENT_SKIP_UPDATE_VERIFY') && FLUENT_EVENT_SKIP_UPDATE_VERIFY),
        ]);
    }

    /**
     * Initialize the class.
     * @param array $config Configuration for the updater.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'type'              => 'plugin', // Default type is 'plugin'.
            'slug'              => '', // Registry slug. Defaults to the plugin directory name.
            'basename'          => '', // Basename for the plugin
            'version'           => '', // Current Version of the plugin
            'api_url'           => '', // API URL for the updater.
            'cache_time'        => 12 * HOUR_IN_SECONDS, // How long a successful lookup is kept.
            'show_check_update' => false, // Show check update link in plugin row meta.
            'verify_checksum'   => true, // Refuse a package whose bytes do not match the published SHA-256.
        ];

        $config = wp_parse_args($config, $defaults);

        // The slug is the registry's only identifier, and what plugins_api
        // matches on, so it must never be left empty. The directory name is the
        // same value WordPress itself uses for this plugin.
        if (empty($config['slug']) && !empty($config['basename'])) {
            $config['slug'] = current(explode('/', $config['basename']));
        }

        $this->config = $config;
        $this->cache_key = 'fsl_' . md5($config['basename'] . '_' . $config['slug']) . '_version_info';

        // Nothing to ask about, or nowhere to ask. Registering hooks now would
        // mean an update check that can only ever fail.
        if (empty($config['basename']) || empty($config['version']) || empty($config['api_url'])) {
            return;
        }

        if ($config['type'] === 'plugin') {
            $this->initPluginUpdaterHooks(); // Initialize the plugin updater hooks.
        }
    }

    /**
     * Run plugin updater hooks.
     *
     * @return void
     */
    private function initPluginUpdaterHooks()
    {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'checkPluginUpdate'));
        add_filter('plugins_api', array($this, 'pluginsApiFilter'), 10, 3);

        // Registered on every request rather than only in the admin, because
        // cron and WP-CLI run updates too and an unguarded path is the one that
        // gets used.
        if ($this->config['verify_checksum']) {
            add_filter('upgrader_pre_download', array($this, 'verifyPackage'), 10, 4);
        }

        if ($this->config['show_check_update']) {

            $getParam = 'fluent_sl_check_update_' . $this->config['slug'];
            $nonceAction = 'fsl_check_update_' . $this->config['slug'];

            add_filter('plugin_row_meta', function ($links, $file) use ($getParam, $nonceAction) {
                // On multisite only a network admin can update plugins, and the link leads to the network screen.
                if ($this->config['basename'] !== $file || !current_user_can('update_plugins')) {
                    return $links;
                }

                $checkUpdateUrl = esc_url(wp_nonce_url($this->pluginsScreenUrl('plugins.php?' . $getParam . '=' . time()), $nonceAction));
                $row_meta = array(
                    'check_update' => '<a style="color: #583fad;font-weight: 600;" href="' . $checkUpdateUrl . '" aria-label="' . esc_attr__('Check Update', 'fluent-event') . '">' . esc_html__('Check Update', 'fluent-event') . '</a>',
                );

                return array_merge($links, $row_meta);

            }, 10, 2);

            if (isset($_GET[$getParam])) {
                add_action('admin_init', function () use ($nonceAction) {
                    if (current_user_can('update_plugins') && check_admin_referer($nonceAction)) {
                        delete_transient($this->cache_key);

                        add_filter('fluent_sl/api_request_query_params', function ($params) {
                            $params['disable_cache'] = 'yes';
                            return $params;
                        });

                        // Remove our filter on the site transient
                        remove_filter('pre_set_site_transient_update_plugins', [$this, 'checkPluginUpdate']);

                        $update_cache = get_site_transient('update_plugins');
                        if ($update_cache && !empty($update_cache->response[$this->config['basename']])) {
                            unset($update_cache->response[$this->config['basename']]);
                        }

                        // Asked for explicitly: the entry was just removed above, so skipping the lookup
                        // here would save the transient without it and hide an available update.
                        $this->forceCheck = true;
                        $update_cache = $this->checkPluginUpdate($update_cache);
                        $this->forceCheck = false;
                        set_site_transient('update_plugins', $update_cache);

                        // Restore our filter
                        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkPluginUpdate']);

                        wp_safe_redirect($this->pluginsScreenUrl('plugins.php?s=' . $this->config['slug'] . '&plugin_status=all'));
                        exit();
                    }
                });
            }
        }
    }

    /**
     * A Plugins-screen URL. On multisite, plugin updates are managed on the network screen;
     * the site screen shows no update notices.
     *
     * @param string $path
     * @return string
     */
    private function pluginsScreenUrl($path)
    {
        return is_multisite() ? network_admin_url($path) : admin_url($path);
    }

    /**
     * Check for Update for this specific plugin.
     *
     * @param Object $transient_data Transient data for update.
     */
    public function checkPluginUpdate($transient_data)
    {
        global $pagenow;

        if (!is_object($transient_data)) {
            $transient_data = new \stdClass();
        }

        if (!$this->forceCheck && 'plugins.php' === $pagenow && is_multisite()) {
            return $transient_data; // If on plugins page in a multisite, skip update check.
        }

        if (!empty($transient_data->response) && !empty($transient_data->response[$this->config['basename']])) {
            return $transient_data;
        }

        $version_info = $this->getVersionInfo();

        if (false !== $version_info && is_object($version_info) && isset($version_info->new_version)) {
            // The full sections payload belongs to the "View details" screen, not
            // to a transient that is loaded on every admin page.
            $version_info = clone $version_info;
            unset($version_info->sections);

            // If new version available then set to `response`.
            if (version_compare($this->config['version'], $version_info->new_version, '<')) {
                $transient_data->response[$this->config['basename']] = $version_info;
            } else {
                // If new version is not available then set to `no_update`.
                $transient_data->no_update[$this->config['basename']] = $version_info;
            }

            $transient_data->last_checked = time();
            $transient_data->checked[$this->config['basename']] = $this->config['version'];
        }

        return $transient_data;
    }

    /**
     * Filter the plugins API response for this specific plugin.
     *
     * @param mixed $data Plugin data.
     * @param string $action The action type.
     * @param object $args Arguments.
     *
     * @return mixed
     */
    public function pluginsApiFilter($data, $action = '', $args = null)
    {
        // must be requesting plugin info.
        if ('plugin_information' !== $action || !$args) {
            return $data;
        }

        // check if this is our plugin or not
        if (!isset($args->slug) || ($args->slug !== $this->config['slug'])) {
            return $data;
        }

        // get the version info.
        $data = $this->getVersionInfo();

        if (!$data) {
            return new \WP_Error('no_data', __('No data found for this plugin', 'fluent-event'));
        }

        return $data;
    }

    /**
     * Refuse a package whose bytes do not match the published checksum.
     *
     * Returning a WP_Error aborts the update before anything is extracted.
     *
     * @param bool|string|\WP_Error $reply
     * @param string $package The package URL WordPress is about to download.
     * @param \WP_Upgrader|null $upgrader
     * @param array $hookExtra
     * @return bool|string|\WP_Error Path to the verified file, or an error.
     */
    public function verifyPackage($reply, $package, $upgrader = null, $hookExtra = [])
    {
        if (false !== $reply) {
            return $reply; // Another filter already resolved the download.
        }

        if (empty($hookExtra['plugin']) || $hookExtra['plugin'] !== $this->config['basename']) {
            return $reply; // Not this plugin.
        }

        // A local path rather than a URL means an admin uploading a zip by hand.
        // The download gate never sees those, and refusing them would break
        // manual installs and repair flows.
        if (!is_string($package) || !preg_match('#^https?://#i', $package)) {
            return $reply;
        }

        $expected = $this->publishedChecksum($this->updateEntry());

        // Nothing to check against. The entry may have been written by something
        // other than our own updater, or cached from a response that predates
        // checksums, so ask the API directly before deciding.
        if (!$expected && $this->canRefetch()) {
            $expected = $this->publishedChecksum($this->fetchFresh());
        }

        // Verification is on (this filter is only registered then), so a package with nothing to
        // check it against is refused, not installed unchecked. Every release of this plugin
        // carries a checksum; FLUENT_EVENT_SKIP_UPDATE_VERIFY is the explicit opt-out.
        if (!$expected) {
            do_action('fluent_event/update_verification_failed', $this->config['slug'], 'checksum_missing');

            return new \WP_Error(
                'fluent_sl_checksum_missing',
                __('The update could not be verified because its checksum is missing, so it was not installed. Please try again later, and contact support if this keeps happening.', 'fluent-event')
            );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        // WordPress normally emits this itself in download_package(). Since we
        // are taking over the download, say so, or the screen sits silent.
        if (is_object($upgrader) && isset($upgrader->skin) && is_object($upgrader->skin)) {
            $upgrader->skin->feedback('downloading_package', $package);
        }

        $file = download_url($package, 300);

        if (is_wp_error($file)) {
            return $file;
        }

        $actual = hash_file('sha256', $file);

        if (is_string($actual) && hash_equals($expected, strtolower($actual))) {
            return $file;
        }

        // A cached check can name a release that has since been superseded, so
        // the bytes on the wire legitimately differ from the hash in hand. Ask
        // once for the current release and re-check the file already downloaded.
        if (is_string($actual) && $this->canRefetch()) {
            $current = $this->publishedChecksum($this->fetchFresh());

            if ($current && hash_equals($current, strtolower($actual))) {
                // Drop the stale response so the plugins screen stops
                // advertising the version it described.
                delete_transient($this->cache_key);

                return $file;
            }
        }

        @unlink($file);

        do_action('fluent_event/update_verification_failed', $this->config['slug'], 'checksum_mismatch');

        return new \WP_Error(
            'fluent_sl_checksum_mismatch',
            __('The downloaded update does not match its published checksum, so it was not installed. Please try again, and contact support if this keeps happening.', 'fluent-event')
        );
    }

    /**
     * The checksum published alongside a release, if it is well formed.
     *
     * @param object|null $update
     * @return string|false lowercase hex, or false
     */
    private function publishedChecksum($update)
    {
        if (!is_object($update) || empty($update->checksum) || !is_string($update->checksum)) {
            return false;
        }

        if (!preg_match('/^[a-f0-9]{64}$/i', $update->checksum)) {
            return false;
        }

        return strtolower($update->checksum);
    }

    /**
     * The update object our own updater put into the transient.
     *
     * @return object|null
     */
    private function updateEntry()
    {
        $transient = get_site_transient('update_plugins');

        if (is_object($transient) && isset($transient->response[$this->config['basename']]) && is_object($transient->response[$this->config['basename']])) {
            return $transient->response[$this->config['basename']];
        }

        return null;
    }

    /** One uncached call to the API per request, and no more. */
    private function canRefetch()
    {
        return !$this->refetched;
    }

    /**
     * Ask the API directly, bypassing every cache.
     *
     * @return object|null
     */
    private function fetchFresh()
    {
        $this->refetched = true;

        $fresh = $this->getRemoteVersionInfo();

        return is_object($fresh) ? $fresh : null;
    }

    /**
     * Get version info from database
     *
     * @return mixed
     */
    private function getCachedVersionInfo()
    {
        global $pagenow;

        // On the update screens, and whenever the user has explicitly asked for
        // a fresh check, go and look.
        if ('update-core.php' === $pagenow || !empty($_GET['force-check'])) {
            return false;
        }

        if ($pagenow === 'plugin-install.php' && !empty($_GET['plugin'])) {
            return false;
        }

        return get_transient($this->cache_key);
    }

    /**
     * Get Plugin Version Info
     */
    private function getVersionInfo()
    {
        $cached = $this->getCachedVersionInfo();

        if (false !== $cached) {
            // A remembered failure. Kept so a down API is not re-asked on every
            // admin page load, but never reported as "no update available".
            return self::FETCH_FAILED === $cached ? false : $cached;
        }

        $versionInfo = $this->getRemoteVersionInfo();

        if ($versionInfo) {
            set_transient($this->cache_key, $versionInfo, $this->config['cache_time']);
        } else {
            // Shorter than a success: a 503 or a network blip should not cost
            // the site half a day of update checks.
            set_transient($this->cache_key, self::FETCH_FAILED, HOUR_IN_SECONDS);
        }

        return $versionInfo;
    }

    private function getRemoteVersionInfo()
    {
        $fullUrl = add_query_arg(apply_filters('fluent_sl/api_request_query_params', array(
            'fluent-cart' => 'get_license_version',
        ), $this->config), $this->config['api_url']);

        $payload = [
            'addon_slug'      => $this->config['slug'],
            'current_version' => $this->config['version'],
            'site_url'        => home_url(),
        ];

        $payload = apply_filters('fluent_sl/updater_payload_' . $this->config['slug'], $payload, $this->config);

        // send the post request to the API.
        $response = wp_remote_post($fullUrl, array(
            'timeout'     => 15,
            'redirection' => 3,
            'body'        => $payload
        ));

        if (is_wp_error($response)) {
            return false; // Return false if there is an error.
        }

        // 404 addon_not_found / addon_unpublished, 503 storage_not_configured,
        // and anything else: not an answer we can write plugin state from.
        if (200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $responseBody = wp_remote_retrieve_body($response);

        if (empty($responseBody)) {
            return false; // Return false if the response body is empty.
        }

        $versionInfo = json_decode($responseBody);
        if (null === $versionInfo || !is_object($versionInfo)) {
            return false; // Return false if the response body is not a valid JSON object.
        }

        // Ensure the version info has the required properties.
        if (empty($versionInfo->new_version) || !is_string($versionInfo->new_version)) {
            return false; // Return false if the required properties are not set.
        }

        // A release with nothing to download is not an update.
        if (empty($versionInfo->package) || !is_string($versionInfo->package)) {
            return false;
        }

        // With verification on, a release the download gate would refuse is not one to offer:
        // a missing or malformed checksum counts as a failed lookup, never as an update.
        if ($this->config['verify_checksum'] && !$this->publishedChecksum($versionInfo)) {
            return false;
        }

        // The registry answers on the slug, but a response describing another
        // product is not one to act on.
        if (!empty($versionInfo->slug) && $versionInfo->slug !== $this->config['slug']) {
            return false;
        }

        $versionInfo->plugin = $this->config['basename'];
        $versionInfo->slug = $this->config['slug'];
        $versionInfo->id = $this->config['basename'];

        // plugins_api reads `version`, the update transient reads `new_version`
        if (empty($versionInfo->version)) {
            $versionInfo->version = $versionInfo->new_version;
        }

        if (!empty($versionInfo->sections)) {
            $versionInfo->sections = (array)$versionInfo->sections; // Ensure sections is an array.
        } else {
            $versionInfo->sections = array();
        }

        // The registry serves neither, and the "View details" screen wants both
        // to exist before it will render.
        $versionInfo->banners = isset($versionInfo->banners) ? (array)$versionInfo->banners : array();
        $versionInfo->icons = isset($versionInfo->icons) ? (array)$versionInfo->icons : array();

        return $versionInfo; // Return the version info object.
    }
}
