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

(new \FluentEvent\App\Hooks\Handlers\EmailNotificationHandler())->register();

$app->addAction('admin_menu', 'AdminMenuHandler');
$app->addAction('in_admin_header', ['AdminMenuHandler', 'removeAdminNotices']);

$app->addCustomAction('exception', 'ExceptionHandler');

/**
 * Registers the fe_event post type and its fe_venue taxonomy.
 */
$app->addAction('init', 'CPTHandler@registerPostTypes');

/**
 * Cascade cleanup rides the deletion actions - never model boot hooks.
 */
(new \FluentEvent\App\Hooks\Handlers\CleanupHandlers\CleanupHandler())->register();

/**
 * FluentCommunity bridge — only registers while FluentCommunity is active. fcom
 * fires portal_loaded at plugins_loaded@10, so subscribe at include time.
 */
if (did_action('fluent_community/portal_loaded')) {
    \FluentEvent\Modules\FluentCommunity\Bootstrap::register();
} else {
    add_action('fluent_community/portal_loaded', ['FluentEvent\Modules\FluentCommunity\Bootstrap', 'register']);
}
