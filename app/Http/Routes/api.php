<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

/** @var \FluentEvent\Framework\Http\Router $router */

use FluentEvent\App\Http\Controllers\EventController;
use FluentEvent\App\Http\Controllers\GuestController;
use FluentEvent\App\Http\Controllers\UserController;
use FluentEvent\App\Http\Controllers\ReportController;
use FluentEvent\App\Http\Controllers\CalendarController;
use FluentEvent\App\Http\Controllers\SettingsController;
use FluentEvent\App\Http\Controllers\CommunityEventController;

$router->withPolicy('ReportPolicy')->group(function ($router) {
    $router->get('/dashboard', 'DashboardController@get');
});

$router->prefix('users')->withPolicy('UserPolicy')->group(function ($router) {
    $router->get('/', [UserController::class, 'getUsers']);
});

$router->prefix('settings')->withPolicy('SettingsPolicy')->group(function ($router) {
    $router->get('/general', [SettingsController::class, 'getGeneral']);
    $router->post('/general', [SettingsController::class, 'updateGeneral']);

    $router->get('/email', [SettingsController::class, 'getEmail']);
    $router->post('/email', [SettingsController::class, 'updateEmail']);

    $router->get('/email-notifications', [SettingsController::class, 'getEmailNotifications']);
    $router->post('/email-notifications', [SettingsController::class, 'updateEmailNotifications']);

    $router->get('/team', [SettingsController::class, 'getTeam']);
    $router->post('/team', [SettingsController::class, 'upsertTeamMember']);
    $router->delete('/team/{userId}', [SettingsController::class, 'removeTeamMember'])->int('userId');
});

$router->prefix('calendars')->withPolicy('CalendarPolicy')->group(function ($router) {
    $router->get('/', [CalendarController::class, 'getAllCalendars']);
    $router->get('/{id}', [CalendarController::class, 'getCalendar'])->int('id');

    // $router->post('/', [CalendarController::class, 'createCalendar']);
    $router->patch('/{id}', [CalendarController::class, 'updateCalendar'])->int('id');
    $router->delete('/{id}', [CalendarController::class, 'deleteCalendar'])->int('id');
});

$router->prefix('reports')->withPolicy('ReportPolicy')->group(function ($router) {
    $router->get('/', [ReportController::class, 'getReports']);
    $router->get('/guest-trends', [ReportController::class, 'getGuestTrends']);
});

$router->prefix('events')->withPolicy('EventPolicy')->group(function ($router) {
    $router->get('/', [EventController::class, 'getAllEvents']);
    $router->get('/{id}', [EventController::class, 'getEvent'])->int('id');

    // $router->post('/', [EventController::class, 'createEvent']);
    $router->patch('/{id}', [EventController::class, 'updateEvent'])->int('id');
    $router->delete('/{id}', [EventController::class, 'deleteEvent'])->int('id');

    $router->get('/{eventId}/guests', [GuestController::class, 'getGuests'])->int('eventId');
    $router->post('/{eventId}/guests', [GuestController::class, 'createGuest'])->int('eventId');
    $router->patch('/{eventId}/guests/{guestId}', [GuestController::class, 'updateGuest'])
        ->int('eventId')->int('guestId');
    $router->delete('/{eventId}/guests/{guestId}', [GuestController::class, 'deleteGuest'])
        ->int('eventId')->int('guestId');
});

$router->prefix('community')->withPolicy('CommunityPolicy')->group(function ($router) {
    $router->get('/events', [CommunityEventController::class, 'getEvents']);
    $router->post('/events/{id}/register', [CommunityEventController::class, 'register'])
        ->int('id')->rateLimitPerMinute(10);
    $router->get('/editor-assets', [CommunityEventController::class, 'getEditorAssets']);
});
