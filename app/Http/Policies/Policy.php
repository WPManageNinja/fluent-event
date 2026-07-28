<?php

namespace FluentEvent\App\Http\Policies;

use FluentEvent\App\Utils\Auth\Auth;
use FluentEvent\Framework\Http\Request\Request;
use FluentEvent\Framework\Foundation\Policy as BasePolicy;

class Policy extends BasePolicy
{
	/**
     * Check user permission for any method.
     * 
     * @param  \FluentEvent\Framework\Http\Request\Request $request
     * @return bool
     */
    public function verifyRequest(Request $request, ...$args)
    {
        return Auth::check($request, 'manage_options', ...$args);
    }	
}
