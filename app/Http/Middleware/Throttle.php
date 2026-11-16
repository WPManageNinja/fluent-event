<?php

namespace FluentEvent\App\Http\Middleware;

use FluentEvent\Framework\Foundation\App;
use FluentEvent\Framework\Http\Middleware\RateLimiter;

// The framework's limiter lets every logged-in request through, so any account could send
// registration emails without limit. This one counts everyone: a member by account, a visitor by IP.
class Throttle extends RateLimiter
{
    public function __construct($perMinute)
    {
        // Doubled as the framework's own rateLimit() does: each request passes through twice.
        parent::__construct($perMinute * 2, MINUTE_IN_SECONDS);
    }

    protected function shouldAllow($request)
    {
        return in_array($request->method(), ['HEAD', 'OPTIONS'], true);
    }

    protected function makeTransientKey($request)
    {
        $who = is_user_logged_in() ? 'user:' . get_current_user_id() : $request->getIp();

        return App::config()->get('app.slug') . '_rate_limit_' . md5($who . '|' . ($request->getRoute() ?: 'unknown'));
    }
}
