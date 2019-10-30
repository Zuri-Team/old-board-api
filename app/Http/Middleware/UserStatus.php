<?php

namespace App\Http\Middleware;


use Auth;
use Cache;
use Carbon\Carbon;
use Closure;

class UserStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        IF(Auth::check())
        {

            $limit = Carbon::now()->addMinutes(2);
            Cache::put('online' . Auth::user()->id, true, $limit);
        }
        return $next($request);
    }
}
