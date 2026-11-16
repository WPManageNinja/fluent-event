<?php if ( ! defined( 'ABSPATH' ) ) { 'cli' === PHP_SAPI || exit; }

/** @var \FluentEvent\Framework\Http\Router $router */

use FluentEvent\App\Http\Controllers\EventController;
use FluentEvent\App\Http\Controllers\GuestController;
use FluentEvent\App\Http\Controllers\UserController;
use FluentEvent\App\Http\Controllers\ReportController;
use FluentEvent\App\Http\Controllers\CalendarController;
use FluentEvent\App\Http\Controllers\SettingsController;
use FluentEvent\App\Http\Controllers\CommunityEventController;
use FluentEvent\App\Http\Controllers\PortalEventController;
use FluentEvent\App\Http\Middleware\Throttle;

$router->withDefaultPolicy()->group(function ($router) {
    $router->get('/dashboard', 'DashboardController@get');
});

$router->prefix('users')->withDefaultPolicy()->group(function ($router) {
    $router->get('/', [UserController::class, 'getUsers']);
});

$router->prefix('settings')->withDefaultPolicy()->group(function ($router) {
    $router->get('/general', [SettingsController::class, 'getGeneral']);
    $router->post('/general', [SettingsController::class, 'updateGeneral']);
    $router->get('/pages', [SettingsController::class, 'getPages']);
    $router->post('/create-page', [SettingsController::class, 'createPage']);

    $router->get('/email', [SettingsController::class, 'getEmail']);
    $router->post('/email', [SettingsController::class, 'updateEmail']);

    $router->get('/email-notifications', [SettingsController::class, 'getEmailNotifications']);
    $router->post('/email-notifications', [SettingsController::class, 'updateEmailNotifications']);
    $router->get('/email-notifications/preview/{type}', [SettingsController::class, 'previewEmail'])->alphaNumDash('type');
});

$router->prefix('calendars')->withDefaultPolicy()->group(function ($router) {
    $router->get('/', [CalendarController::class, 'getAllCalendars']);
    $router->get('/options', [CalendarController::class, 'getCalendarOptions']);
    $router->get('/{id}', [CalendarController::class, 'getCalendar'])->int('id');

    $router->post('/', [CalendarController::class, 'createCalendar']);
    $router->patch('/{id}', [CalendarController::class, 'updateCalendar'])->int('id');
    $router->delete('/{id}', [CalendarController::class, 'deleteCalendar'])->int('id');
});

$router->prefix('reports')->withDefaultPolicy()->group(function ($router) {
    $router->get('/', [ReportController::class, 'getReports']);
    $router->get('/guest-trends', [ReportController::class, 'getGuestTrends']);
});

$router->prefix('events')->withDefaultPolicy()->group(function ($router) {
    $router->get('/', [EventController::class, 'getAllEvents']);
    $router->get('/by-slug', [EventController::class, 'getEventBySlug']);
    $router->get('/{id}', [EventController::class, 'getEvent'])->int('id');

    $router->post('/', [EventController::class, 'createEvent']);
    $router->patch('/{id}', [EventController::class, 'updateEvent'])->int('id');
    $router->delete('/{id}', [EventController::class, 'deleteEvent'])->int('id');

    $router->get('/{eventId}/guests', [GuestController::class, 'getGuests'])->int('eventId');
    $router->post('/{eventId}/guests', [GuestController::class, 'createGuest'])->int('eventId');
    $router->post('/{eventId}/guests/bulk-action', [GuestController::class, 'handleBulkActions'])->int('eventId');
    $router->patch('/{eventId}/guests/{guestId}', [GuestController::class, 'updateGuest'])
        ->int('eventId')->int('guestId');
    $router->delete('/{eventId}/guests/{guestId}', [GuestController::class, 'deleteGuest'])
        ->int('eventId')->int('guestId');
});

$router->prefix('community')->withPolicy('CommunityPolicy')->group(function ($router) {
    $router->get('/events', [CommunityEventController::class, 'getEvents']);
    $router->get('/events/by-slug', [CommunityEventController::class, 'getEventBySlug']);
    $router->post('/events/{id}/register', [CommunityEventController::class, 'register'])
        ->int('id')->before(new Throttle(10));
    $router->post('/events/{id}/register-guest', [CommunityEventController::class, 'registerGuest'])
        ->int('id')->before(new Throttle(5));
});

// The public event page's registration form.
$router->prefix('portal')->withPolicy('PortalPolicy')->group(function ($router) {
    $router->post('/events/{id}/register-guest', [PortalEventController::class, 'registerGuest'])
        ->int('id')->before(new Throttle(5));
});
