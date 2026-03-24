<?php

use App\Helpers\AppConfigurator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            AppConfigurator::configureRoutes();
        },
    )
    ->withEvents(
        discover: [__DIR__.'/../app/Listeners', __DIR__.'/../app/Events'],
    )
    ->withMiddleware(
        fn (Middleware $middleware) => AppConfigurator::configureMiddleware(
            $middleware,
        ),
    )
    ->withSchedule(fn () => AppConfigurator::configureSchedule())
    ->withExceptions(
        fn (Exceptions $exceptions) => AppConfigurator::configureExceptions(
            $exceptions,
        ),
    )
    ->create();
