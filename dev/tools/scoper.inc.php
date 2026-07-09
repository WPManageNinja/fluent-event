<?php

/**
 * PHP-Scoper configuration for WPFluent plugins.
 *
 * Isolates third-party vendor packages under the plugin's namespace.
 * WPFluent framework packages are excluded — they have their own
 * namespace rewriting handled by ComposerScript.
 */

$rootComposer = json_decode(
    file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
    true
);

$namespace = $rootComposer['extra']['wpfluent']['namespace']['current'] ?? null;

if (!$namespace) {
    throw new RuntimeException('Plugin namespace not found in composer.json');
}

$prefix = $namespace . '\\Vendor';

return [
    'prefix' => $prefix,

    'finders' => [],

    // Don't touch these namespaces
    'exclude-namespaces' => [
        // Plugin's own code
        $namespace,
        $namespace . '\Framework',

        // WPFluent framework (already rewritten by ComposerScript)
        'WPFluent',
        'WPFluentPackage',
        'WpfluentPackage',

        // WordPress
        '/^WP_/',
    ],

    'exclude-classes' => [
        'WP_*',
        'wpdb',
        'Walker',
        'Walker_*',
        'WP_Widget',
        'WP_Customize_*',
        'PHPMailer',
    ],

    'exclude-functions' => [
        // WordPress
        'wp_*', 'get_option', 'update_option', 'delete_option',
        'add_action', 'add_filter', 'do_action', 'apply_filters',
        'remove_action', 'remove_filter', 'has_action', 'has_filter',
        '__', '_e', '_n', '_x', 'esc_html__', 'esc_html_e',
        'esc_attr__', 'esc_attr_e', 'esc_html', 'esc_attr', 'esc_url',
        'absint', 'wp_die', 'wp_redirect', 'wp_safe_redirect',
        'wp_send_json', 'wp_send_json_success', 'wp_send_json_error',
        'plugin_dir_path', 'plugin_dir_url', 'plugins_url',
        'home_url', 'admin_url', 'site_url',
        'dbDelta', 'current_user_can', 'wp_verify_nonce', 'wp_create_nonce',
        'sanitize_text_field', 'sanitize_title', 'sanitize_email',
        'trailingslashit', 'untrailingslashit', 'wp_parse_args',
        'get_transient', 'set_transient', 'delete_transient',
    ],

    'exclude-constants' => [
        // WordPress constants
        'ABSPATH', 'WPINC', 'WP_CONTENT_DIR', 'WP_PLUGIN_DIR',
        'MINUTE_IN_SECONDS', 'HOUR_IN_SECONDS', 'DAY_IN_SECONDS',
        'WEEK_IN_SECONDS', 'MONTH_IN_SECONDS', 'YEAR_IN_SECONDS',
        'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST',
        'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY',
        'ARRAY_A', 'ARRAY_N', 'OBJECT', 'OBJECT_K',
        'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
        // PHP constants
        'PHP_INT_MAX', 'PHP_INT_MIN', 'PHP_INT_SIZE', 'PHP_EOL',
        'PHP_MAJOR_VERSION', 'PHP_MINOR_VERSION', 'PHP_VERSION',
        'STDIN', 'STDOUT', 'STDERR',
        'TRUE', 'FALSE', 'NULL',
        'DIRECTORY_SEPARATOR', 'PATH_SEPARATOR',
    ],

    'patchers' => [],
];
