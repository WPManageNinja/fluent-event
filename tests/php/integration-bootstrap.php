<?php
/**
 * Bootstrap for the integration tier: loads the real WordPress this plugin is
 * installed in and talks to its real database.
 *
 * SAFETY — every test runs inside a transaction rolled back in tearDown(), which
 * is what lets this point at a working dev site. Three rules keep that true:
 *
 *   1. Never TRUNCATE or DROP anything.
 *   2. No DDL inside a test. CREATE/ALTER/DROP implicitly COMMIT in MySQL, which
 *      ends the transaction and makes every later write permanent. No migrations.
 *   3. No COMMIT. DB::transaction() is safe: the harness opens the outer
 *      transaction on the framework connection, so inner ones become savepoints.
 *
 * The rollback covers the database only. Mail and outbound HTTP are blocked in
 * IntegrationTestCase, and the Redis object cache is switched off below, since a
 * rollback can't take back a cached copy of a row that no longer exists.
 */

if (getenv('FE_INTEGRATION') !== '1') {
    fwrite(STDERR, "\n[fluent-event integration] Refusing to run: this tier writes to a real WordPress database.\n"
        . "Enable it deliberately:\n\n"
        . "    FE_INTEGRATION=1 php -d memory_limit=1G tests/vendor/bin/phpunit -c phpunit.integration.xml\n\n");
    exit(1);
}

// <wp-root>/wp-content/plugins/fluent-events/tests/php
$wpPath = rtrim(getenv('FE_WP_PATH') ?: dirname(__DIR__, 5), '/');

if (!file_exists($wpPath . '/wp-load.php')) {
    fwrite(STDERR, "\n[fluent-event integration] No wp-load.php in {$wpPath}. Set FE_WP_PATH to the WordPress root.\n\n");
    exit(1);
}

// rest_do_request() doesn't define this, and without it Controller::validate() swallows a 422 into a 200.
define('REST_REQUEST', true);

// The Redis drop-in skips itself on this, leaving WordPress's per-process in-memory cache.
define('WP_REDIS_DISABLED', true);

// These would run before the first transaction opens, so anything they wrote would persist.
define('DISABLE_WP_CRON', true);
define('DISALLOW_FILE_MODS', true);
define('AUTOMATIC_UPDATER_DISABLED', true);

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'authlab.test';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['HTTPS'] = 'on';

// The community bridge waits for fcom's "Enable FluentEvent" switch; the suite runs with it on,
// whatever this site has saved. Pre-seeded the way WordPress allows before plugin.php loads.
$GLOBALS['wp_filter']['fluent_community/portal_loaded'][99][] = [
    'function'      => function ($app) {
        if (!did_action('fluent_community/fluent_event/init')) {
            do_action('fluent_community/fluent_event/init', $app);
        }
    },
    'accepted_args' => 1,
];

require_once $wpPath . '/wp-load.php';

if (!defined('FLUENT_EVENT_PLUGIN_VERSION')) {
    fwrite(STDERR, "\n[fluent-event integration] WordPress loaded but FluentEvent is not active on {$wpPath}.\n\n");
    exit(1);
}

if (wp_using_ext_object_cache()) {
    fwrite(STDERR, "\n[fluent-event integration] A persistent object cache is active; rolled-back rows would survive in it. Refusing to run.\n\n");
    exit(1);
}

fwrite(STDERR, sprintf(
    "[integration] WordPress %s · FluentEvent %s · prefix %s · FluentCommunity %s\n",
    get_bloginfo('version'),
    FLUENT_EVENT_PLUGIN_VERSION,
    $GLOBALS['wpdb']->prefix,
    defined('FLUENT_COMMUNITY_PLUGIN_VERSION') ? FLUENT_COMMUNITY_PLUGIN_VERSION : 'inactive'
));
