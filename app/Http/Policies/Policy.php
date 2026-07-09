<?php

namespace FluentEvents\App\Http\Policies;

use FluentEvents\App\Utils\Auth\Auth;
use FluentEvents\Framework\Http\Request\Request;
use FluentEvents\Framework\Foundation\Policy as BasePolicy;

class Policy extends BasePolicy
{
	/**
     * Check user permission for any method.
     * 
     * @param  \FluentEvents\Framework\Http\Request\Request $request
     * @return bool
     */
    public function verifyRequest(Request $request, ...$args)
    {
        return Auth::check($request, 'manage_options', ...$args);
    }	
}
