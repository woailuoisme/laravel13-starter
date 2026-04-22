<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Meilisearch\Client as MeilisearchClient;

it('verifies redis, database, scout, garage, and queue successfully', function (): void {
    Storage::fake('garage');
    config(['scout.driver' => 'meilisearch']);
    config(['queue.default' => 'database']);

    $meilisearchClient = Mockery::mock(MeilisearchClient::class);
    $meilisearchClient->shouldReceive('health')
        ->once()
        ->andReturn(['status' => 'available']);
    app()->instance(MeilisearchClient::class, $meilisearchClient);

    DB::shouldReceive('connection')
        ->once()
        ->withAnyArgs()
        ->andReturn(new class () {
            public function selectOne(string $query): object
            {
                return (object) ['ok' => 1];
            }
        });

    Redis::shouldReceive('connection')
        ->once()
        ->withAnyArgs()
        ->andReturn(new class () {
            public function ping(): string
            {
                return 'PONG';
            }
        });

    Queue::shouldReceive('connection')
        ->once()
        ->withAnyArgs()
        ->andReturn(new class () {
            public function size(string $queue): int
            {
                return 0;
            }
        });

    $this->artisan('app:verify-services')
        ->expectsOutputToContain('Database')
        ->expectsOutputToContain('Redis')
        ->expectsOutputToContain('Scout')
        ->expectsOutputToContain('disk')
        ->expectsOutputToContain('Queue')
        ->expectsOutputToContain('验证完成，全部通过。')
        ->assertExitCode(0);
});

it('fails when scout is not configured for meilisearch', function (): void {
    Storage::fake('garage');
    config(['scout.driver' => 'collection']);
    config(['queue.default' => 'database']);

    DB::shouldReceive('connection')
        ->once()
        ->withAnyArgs()
        ->andReturn(new class () {
            public function selectOne(string $query): object
            {
                return (object) ['ok' => 1];
            }
        });

    Redis::shouldReceive('connection')
        ->once()
        ->withAnyArgs()
        ->andReturn(new class () {
            public function ping(): string
            {
                return 'PONG';
            }
        });

    Queue::shouldReceive('connection')
        ->once()
        ->withAnyArgs()
        ->andReturn(new class () {
            public function size(string $queue): int
            {
                return 0;
            }
        });

    $this->artisan('app:verify-services')
        ->expectsOutputToContain('Scout')
        ->expectsOutputToContain('验证完成，存在失败项。')
        ->assertExitCode(1);
});
