<?php

use App\Http\Middleware\NoCacheResponse;
use App\Http\Middleware\SwafiAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        |--------------------------------------------------------------------------
        | Middleware personalizado SWAFI
        |--------------------------------------------------------------------------
        | Se registra un alias para proteger las rutas internas del sistema.
        */

        $middleware->web(append: [
            NoCacheResponse::class,
        ]);

        $middleware->alias([
            'swafi.auth' => SwafiAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
