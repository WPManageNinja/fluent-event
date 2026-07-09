<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

$router->namespace('FluentEvents\App\Http\Controllers')
	->withDefaultPolicy()
	->group(fn($router) => require_once __DIR__ . "/api.php");
