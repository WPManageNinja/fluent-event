<?php

namespace FluentEvent\App\Http\Middleware;

class Can
{
	/**
	 * Handle the request
	 * 
	 * @param  \FluentEvent\Framework\Http\Request\Request $request
	 * @param  \Closure $next
	 * @param  array $params
	 * @return mixed
	 */
	public function handle($request, \Closure $next, ...$params)
	{
		if (isset($params[0]) && $this->has($params[0])) {
			return $next($request);
		}

		// return false or nothing for null, the Rest API will handle the response as
		// rest_forbidden (status:403) or call $request->abort to send a custom
		// response. This call will simply call Response::json method.
		return $request->abort(/*int code, string message*/);
	}

	/**
	 * Permission resolver. Return true to allow the request through `handle()`,
	 * false to block it (which triggers `$request->abort()` above).
	 *
	 * Default stub returns true — swap in your real permission lookup here
	 * (role check, cap check, token validation, etc.). `$permission` is
	 * whatever the route passes after the colon in `->before('can:<x>')` —
	 * typed `mixed` because `...$params` on `handle()` has no element-type
	 * annotation, so PHPStan can't narrow each entry to string without a
	 * cast. Narrow it inside this method if your check requires a string.
	 *
	 * @param  mixed  $permission
	 * @return bool
	 */
	protected function has($permission)
	{
		// Implement your permission checking mechanism
		// and return true on success, otherwise false.
		return true;
	}
}
