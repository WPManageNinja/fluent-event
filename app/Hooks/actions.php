<?php defined('ABSPATH') || exit;

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
$app->addAction('admin_enqueue_scripts', ['AdminMenuHandler', 'enqueueAdminPageAssets']);

/**
 * Registers the fevent_event post type. Classification taxonomies come later.
 */
$app->addAction('init', 'CPTHandler@registerPostTypes');

(new \FluentEvent\App\Hooks\Handlers\FrontEndHandler())->register();
(new \FluentEvent\App\Hooks\Handlers\BlockEditorHandler())->register();

$app->addAction('init', 'ActivationHandler@maybeUpgrade');

/**
 * Cascade cleanup rides the deletion actions - never model boot hooks.
 */
(new \FluentEvent\App\Hooks\Handlers\CleanupHandlers\CleanupHandler())->register();

/**
 * FluentCommunity bridge — fcom fires fluent_event/init (during portal_loaded, plugins_loaded@10)
 * only once an admin enables FluentEvent in its Features, so subscribe at include time.
 */
if (did_action('fluent_community/fluent_event/init')) {
    \FluentEvent\Modules\FluentCommunity\Bootstrap::register();
} else {
    add_action('fluent_community/fluent_event/init', ['FluentEvent\Modules\FluentCommunity\Bootstrap', 'register'], 10, 2);
}
