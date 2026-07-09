<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

$router->menu('primary', function ($router) {
    $router->add('/', 'modules/dashboard')
        ->name('dashboard')
        ->icon('HomeFilled')
        ->title(__('Dashboard', 'fluent-event'))
        ->middleware('auth');
});
