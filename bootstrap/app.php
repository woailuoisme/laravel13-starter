<?php

use App\Helpers\AppConfigurator;
use Illuminate\Foundation\Application;

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
    ->withMiddleware(AppConfigurator::configureMiddleware(...))
    ->withSchedule(AppConfigurator::configureSchedule(...))
    ->withExceptions(AppConfigurator::configureExceptions(...))
    ->create();
