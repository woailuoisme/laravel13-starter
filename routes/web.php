<?php

declare(strict_types=1);

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', static fn (): JsonResponse => Response::json(
    data: [
        'name' => Config::string('app.name', 'Laravel'),
        'env' => App::environment(),
        'version' => App::version(),
        'status' => 'healthy',
        'timestamp' => Date::now()->toIso8601String(),
    ],
))->middleware('doNotCacheResponse');

Route::get('/up', static function (): JsonResponse {
    $isHealthy = rescue(
        callback: static function (): bool {
            Event::dispatch(new DiagnosingHealth);

            return true;
        },
        rescue: false,
        report: static fn (Throwable $e): bool => ! App::hasDebugModeEnabled(),
    );

    return Response::json(
        data: [
            'status' => $isHealthy ? 'up' : 'down',
            'timestamp' => Date::now()->toIso8601String(),
            'services' => [
                'database' => $isHealthy ? 'ok' : 'error',
            ],
        ],
        status: $isHealthy ? 200 : 500,
    );
})->middleware('doNotCacheResponse');

Route::get('/ready', static fn (): JsonResponse => Response::json(
    data: [
        'status' => 'ok',
        'timestamp' => Date::now()->toIso8601String(),
    ],
))->middleware('doNotCacheResponse');
