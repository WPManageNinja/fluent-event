<?php 

defined('ABSPATH') or die;

/**
 * Plugin Name: FluentEvent
 * Plugin URI: https://fluentevent.com
 * Description: Event management plugin for WordPress. 
 * Version: 1.0.1
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: WPManageNinja LLC
 * Author URI: https://fluentevent.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fluent-event
 * Domain Path: /language
 */

define('FLUENT_EVENT_PLUGIN_VERSION', '1.0.1');
define('FLUENT_EVENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLUENT_EVENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLUENT_EVENT_DIR_FILE', __FILE__);
define('FLUENT_EVENT_START_TIME', microtime(true));

if(!defined('FLUENT_EVENT_UPLOAD_DIR')) {
  define('FLUENT_EVENT_UPLOAD_DIR', 'fluent-event');
}

require __DIR__.'/boot/action_scheduler_loader.php';

return (function($boot, $_autoload) {
    return $boot(__FILE__);
})(
    require __DIR__.'/boot/app.php',
    require __DIR__.'/vendor/autoload.php'
);
