<?php

namespace FluentEvents\App\Http\Controllers;

use FluentEvents\Framework\Http\Request\Request;

class DashboardController extends Controller
{
    /**
     * GET /dashboard — returns the payload consumed by the Dashboard Vue
     * module. Keep the shape flat (just `message` for now) so the frontend's
     * commented-out `$get('dashboard')` call in modules/dashboard/index.vue
     * can read `response.message` directly.
     *
     * @param  \FluentEvents\Framework\Http\Request\Request  $request
     * @return array{message: string}
     */
    public function get(Request $request)
    {
        return [
            'message' => __('Welcome to the dashboard.', 'fluent-events'),
        ];
    }
}
