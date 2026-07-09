<?php 

defined('ABSPATH') or die;

/**
 * Plugin Name: Fluent Events 
 * Plugin URI: https://fluentevents.com
 * Description: Event management plugin for WordPress. 
 * Version: 1.0.0
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Author: WPManageNinja LLC
 * Author URI: https://fluentevents.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fluent-events
 * Domain Path: /language
 * Update URI: false
 */

define('FLUENT_EVENTS_PLUGIN_VERSION', '1.0.0');
define('FLUENT_EVENTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLUENT_EVENTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLUENT_EVENTS_DIR_FILE', __FILE__);
define('FLUENT_EVENTS_START_TIME', microtime(true));
define('FLUENT_EVENTS_BD_VERSION', '1.0.0');

if(!defined('FLUENT_EVENTS_UPLOAD_DIR')) {
  define('FLUENT_EVENTS_UPLOAD_DIR', 'fluent-events');
}

return (function($boot, $_autoload) {
    return $boot(__FILE__);
})(
    require __DIR__.'/boot/app.php',
    require __DIR__.'/vendor/autoload.php'
);
