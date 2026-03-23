<?php

use Lanin\Laravel\ApiDebugger\Collections\CacheCollection;
use Lanin\Laravel\ApiDebugger\Collections\MemoryCollection;
use Lanin\Laravel\ApiDebugger\Collections\ProfilingCollection;
use Lanin\Laravel\ApiDebugger\Collections\QueriesCollection;

return [
    'enabled' => (bool) env('API_DEBUGGER_ENABLED', env('APP_DEBUG', false)),
    /**
     * Specify what data to collect.
     */
    'collections' => [
        // Database queries.
        QueriesCollection::class,

        // Show cache events.
        CacheCollection::class,

        // Profile custom events.
        ProfilingCollection::class,

        // Memory usage.
        MemoryCollection::class,
    ],

    'response_key' => env('API_DEBUGGER_KEY', 'debug'),
];
