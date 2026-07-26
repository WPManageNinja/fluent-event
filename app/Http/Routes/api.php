<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

/** @var $router FluentEvent\Framework\Http\Router */

$router->get('/dashboard', 'DashboardController@get');
