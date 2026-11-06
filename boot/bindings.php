<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

/**
 * Add only the plugin specific bindings here.
 *
 * @var $app FluentEvent\Framework\Foundation\Application
 */

$app->bind(FluentEvent\Framework\Validator\Validator::class, function () {
    return new FluentEvent\App\Utils\CustomValidator();
});

// Note: To enable async requests, uncomment the line below

// (new FluentEvent\Framework\Http\Client)->registerAsyncRequestHandler();