<?php

use App\Jobs\TestHorizonJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

test('test horizon job writes correct log', function () {
    Log::shouldReceive('info')
        ->once()
        ->with('Horizon test job executed successfully.');

    (new TestHorizonJob())->handle();
});

test('test horizon job is registered in scheduler', function () {
    Artisan::call('schedule:list');

    $schedule = app(Schedule::class);

    $hasJob = collect($schedule->events())->contains(function ($event) {
        return str_contains((string) $event->command, 'queue:work')
            || str_contains((string) $event->description, 'TestHorizonJob')
            || str_contains((string) $event->description, TestHorizonJob::class);
    });

    expect($hasJob)->toBeTrue();
});
