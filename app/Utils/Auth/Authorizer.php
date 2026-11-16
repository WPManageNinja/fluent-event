<?php

namespace FluentEvent\App\Utils\Auth;

use Exception;
use FluentEvent\App\App;
use FluentEvent\Framework\Http\Request\Request;

class Authorizer
{
    /**
     * Get the currently logged in user.
     *
     * @return mixed
     */
    public function user()
    {
        return App::user();
    }

    /**
     * Check if the current user has the capability.
     *
     * @param  \FluentEvent\Framework\Http\Request\Request $request
     * @param  string   $capability
     * @param  mixed    ...$args
     * @return bool
     */
    public function check(Request $request, $capability, ...$args)
    {
        try {
            return $this->authorize($request, $capability, ...$args);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Authorize the request based on user capabilities.
     *
     * @param  \FluentEvent\Framework\Http\Request\Request $request
     * @param  string   $capability
     * @param  mixed    ...$args
     * @return bool
     */
    public function authorize(Request $request, $capability, ...$args)
    {
        return $request->user()->can($capability);
    }
}
