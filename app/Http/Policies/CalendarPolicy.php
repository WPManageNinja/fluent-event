<?php

namespace FluentEvent\App\Http\Policies;

use FluentEvent\App\Utils\Auth\Auth;
use FluentEvent\Framework\Http\Request\Request;

class CalendarPolicy extends Policy
{
    public function verifyRequest(Request $request, ...$args): bool
    {
        return Auth::check($request, 'manage_options', ...$args);
    }

    public function index(Request $request, ...$args): bool
    {
        return Auth::check($request, 'manage_options', ...$args);
    }

    public function getCalendar(Request $request, ...$args): bool
    {
        return Auth::check($request, 'manage_options', ...$args);
    }

    public function createCalendar(Request $request, ...$args): bool
    {
        return Auth::check($request, 'manage_options', ...$args);
    }

    public function updateCalendar(Request $request, ...$args): bool
    {
        return Auth::check($request, 'manage_options', ...$args);
    }

    public function deleteCalendar(Request $request, ...$args): bool
    {
        return Auth::check($request, 'manage_options', ...$args);
    }
}
