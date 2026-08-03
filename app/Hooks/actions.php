<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

require_once __DIR__ . '/logoicon.php';

/**
 * All registered action's handlers should be in app\Hooks\Handlers,
 * addAction is similar to add_action and addCustomAction is just a
 * wrapper over add_action which will add a prefix to the hook name
 * using the plugin slug to make it unique in all wordpress plugins,
 * ex: $app->addCustomAction('foo', ['FooHandler', 'handleFoo']) is
 * equivalent to add_action('slug-foo', ['FooHandler', 'handleFoo']).
 */

/**
 * @var $app FluentEvent\Framework\Foundation\Application
 */

$app->addAction('admin_menu', 'AdminMenuHandler');
$app->addAction('in_admin_header', ['AdminMenuHandler', 'removeAdminNotices']);

$app->addCustomAction('exception', 'ExceptionHandler');

/**
 * Registers the fe_event post type. Classification taxonomies come later.
 */
$app->addAction('init', 'CPTHandler@registerPostTypes');
