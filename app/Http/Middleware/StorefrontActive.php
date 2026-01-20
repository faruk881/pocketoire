<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StorefrontActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $status = auth()->user()->storefront->status;

        if(in_array($status,['pending','rejected','banned'])) {
            return apiError('The storefront is '.$status,403);
        }
        
        return $next($request);
    }
}
