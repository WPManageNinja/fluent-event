<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

// Use favicon from resources/images/favicon.png in dev mode
$app->addAction('admin_head', function () use ($app) {
    if (!str_starts_with($app->env(), 'dev')) return;
    
    $screen = get_current_screen();
    $slug = $app->config->get('app.slug');
    if ($screen && $screen->id === "toplevel_page_$slug") {
        $url = plugin_dir_url($app->__pluginfile__) . 'resources/images/favicon.png';
        $file = plugin_dir_path($app->__pluginfile__) . 'resources/images/favicon.png';
        if (file_exists($file)) {
            echo '<link rel="icon" href="' . esc_url($url) . '" sizes="32x32" />';
            echo '<link rel="shortcut icon" href="' . esc_url($url) . '" />';
        }
    }
});

// Add style for menu icon (WP left navigation bar)
$app->addAction('admin_enqueue_scripts', function() use ($app) {
    $slug = sanitize_key($app->config->get('app.slug'));
    wp_register_style('fluent-event-menu-icon', false, [], FLUENT_EVENT_PLUGIN_VERSION);
    wp_enqueue_style('fluent-event-menu-icon');
    wp_add_inline_style('fluent-event-menu-icon', 'li.toplevel_page_' . $slug . ' .wp-menu-image img { width: 20px; height: 20px; }');
});
