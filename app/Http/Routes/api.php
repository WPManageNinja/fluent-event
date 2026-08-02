<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

/** @var $router FluentEvent\Framework\Http\Router */

use FluentEvent\App\Http\Controllers\CalendarController;

$router->get('/dashboard', 'DashboardController@get');

$router->prefix('calendars')->withPolicy('CalendarPolicy')->group(function ($router) {
    $router->get('/', [CalendarController::class, 'getAllCalendars']);
    $router->post('/', [CalendarController::class, 'createCalendar']);

    $router->get('/{id}', [CalendarController::class, 'getCalendar'])->int('id');
    $router->patch('/{id}', [CalendarController::class, 'updateCalendar'])->int('id');
    $router->delete('/{id}', [CalendarController::class, 'deleteCalendar'])->int('id');
});
