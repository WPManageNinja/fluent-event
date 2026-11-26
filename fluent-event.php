<?php 

defined('ABSPATH') or die;

/**
 * Plugin Name: FluentEvent
 * Description: Create events, collect RSVPs and registrations, and send confirmations and reminders to your guests.
 * Version: 1.2.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: WPManageNinja LLC
 * Author URI: https://wpmanageninja.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fluent-event
 * Domain Path: /language
 */

define('FLUENT_EVENT_PLUGIN_VERSION', '1.2.0');
define('FLUENT_EVENT_DB_VERSION', '1.0.2');
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
