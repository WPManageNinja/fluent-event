<?php

namespace FluentEvent\App\Http\Controllers;

use FluentEvent\App\App;

use FluentEvent\Framework\Http\Controller as BaseController;

abstract class Controller extends BaseController
{
    /**
     * Resolve an entry from the container, or return the container itself
     * when no key is given. Handy shorthand inside controller actions:
     *
     *     $this->app('db')->table(...);
     *     $this->app(UserService::class)->create(...);
     *
     * @param  string|null  $module  Service key or FQCN to resolve. Pass null
     *                               to get the Application instance itself.
     * @return mixed
     */
    public function app($module = null)
    {
        return App::make($module);
    }
}
