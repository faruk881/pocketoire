<?php

use App\Http\Middleware\Admin;
use App\Http\Middleware\Creator;
use App\Http\Middleware\StorefrontActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'creator' => Creator::class,
            'admin' => Admin::class,
            'storefrontActive' => StorefrontActive::class,
            'canChangePassword' => \App\Http\Middleware\CanChangePassword::class,
        ]);
    })
    ->withMiddleware(function ($middleware) {
        $middleware->append(HandleCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
