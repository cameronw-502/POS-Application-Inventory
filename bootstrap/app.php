<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\ValidateApiKey;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', 
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register your middleware aliases here
        $middleware->alias([
            'api.key' => ValidateApiKey::class,
        ]);
        
        // You could also add other middleware configurations here
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
