<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Creator
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(!auth()->check()) {
            return apiError('Unauthenticated.', 401);
        }

        $user = Auth()->user();

        if($user->account_type !== 'creator'){
            return apiError('Unauthorized. Creators only.',403);
        }

        if(in_array($user->status,['suspended','banned'])) {
            return apiError('Your account is '.$user->status.'. Please contact admin',403);
        }

        return $next($request);
    }
}
