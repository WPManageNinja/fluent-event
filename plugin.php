<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

/**
 * Plugin Name: Fluent Events
 * Plugin URI: https://fluentevents.com
 * Description: Event management plugin for Wordpress
 * Version: 1.0.0
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Author: WPManageNinja LLC
 * Author URI: https://fluentevents.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fluent-event
 * Domain Path: /language
 * Update URI: false
 */

/*************** Code IS Poetry **************/
return (function($boot, $_autoload) {

    return $boot(__FILE__);
})(
    require __DIR__.'/boot/app.php',

    require __DIR__.'/vendor/autoload.php'
);
/************ Built With WPFluent *************/
