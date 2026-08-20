<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

/** @var $router FluentEvent\Framework\Http\Router */

use FluentEvent\App\Http\Controllers\EventController;
use FluentEvent\App\Http\Controllers\CalendarController;

$router->get('/dashboard', 'DashboardController@get');

$router->prefix('calendars')->withPolicy('CalendarPolicy')->group(function ($router) {
    $router->get('/', [CalendarController::class, 'getAllCalendars']);
    $router->get('/{id}', [CalendarController::class, 'getCalendar'])->int('id');

    $router->post('/', [CalendarController::class, 'createCalendar']);
    $router->patch('/{id}', [CalendarController::class, 'updateCalendar'])->int('id');
    $router->delete('/{id}', [CalendarController::class, 'deleteCalendar'])->int('id');
});

$router->prefix('events')->withPolicy('EventPolicy')->group(function ($router) {
    $router->post('/', [EventController::class, 'createEvent']);
});
