<?php

use App\Helpers\AppConfigHelper;
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
            AppConfigHelper::configureRoutes();
        },
    )
    ->withEvents(
        discover: [__DIR__.'/../app/Listeners', __DIR__.'/../app/Events'],
    )
    ->withMiddleware(
        fn (Middleware $middleware) => AppConfigHelper::configureMiddleware(
            $middleware,
        ),
    )
    ->withSchedule(fn () => AppConfigHelper::configureSchedule())
    ->withExceptions(
        fn (Exceptions $exceptions) => AppConfigHelper::configureExceptions(
            $exceptions,
        ),
    )
    ->create();
