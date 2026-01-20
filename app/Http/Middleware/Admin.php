<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Admin
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

        $user = auth()->user();

        if($user->account_type !== 'admin'){
            return apiError('Unauthorized. Admins only.',403);
        }
        return $next($request);
    }
}
