<?php 

defined('ABSPATH') or die;

/**
 * Plugin Name: Fluent Events 
 * Description: Event management plugin for WordPress. 
 * Version: 1.0.0
 * Author: WPManageNinja LLC
 * Author URI: https://fluentevents.com
 * Plugin URI: https://fluentevents.com
 * License: GPLv2 or later
 * Text Domain: fluent-events
 * Domain Path: /language
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

require __DIR__ . '/vendor/autoload.php';

call_user_func(function ($bootstrap) {
  $bootstrap(__FILE__);
}, require(__DIR__ . '/boot/app.php'));