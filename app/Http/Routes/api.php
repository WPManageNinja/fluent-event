<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

/** @var $router FluentEvents\Framework\Http\Router */

$router->get('/dashboard', 'DashboardController@get');
