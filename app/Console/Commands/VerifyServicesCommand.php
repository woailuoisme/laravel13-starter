<?php

declare(strict_types=1);

namespace App\Console\Commands;

use denis660\Centrifugo\Centrifugo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use Meilisearch\Client as MeilisearchClient;
use Throwable;

#[Signature(
    'app:verify-services {--database=default : 数据库连接名} {--redis=default : Redis 连接名} {--disk=garage : 存储磁盘}',
)]
#[Description('验证 Redis、数据库、Scout(Meilisearch)、Garage、Centrifugo 和队列是否可用')]
class VerifyServicesCommand extends Command
{
    public function handle(): int
    {
        $checks = [
            'database' => $this->checkDatabase($this->resolveDatabaseConnectionName((string) $this->option(
                'database',
            ))),
            'redis' => $this->checkRedis((string) $this->option('redis')),
            'scout' => $this->checkScoutMeilisearch(),
            'garage' => $this->checkGarage((string) $this->option('disk')),
            'queue' => $this->checkQueue(),
            'horizon' => $this->checkHorizon(),
            'centrifugo' => $this->checkCentrifugo(),
        ];

        /** @var array<string, string> $labels */
        $labels = [
            'database' => 'Database',
            'redis' => 'Redis',
            'scout' => 'Scout',
            'garage' => 'disk',
            'queue' => 'Queue',
            'horizon' => 'Horizon',
            'centrifugo' => 'Centrifugo',
        ];

        $hasFailure = false;

        foreach ($checks as $name => $check) {
            $hasFailure = $hasFailure || ! $check['ok'];

            $prefix = $check['ok'] ? '<info>[OK]</info>' : '<error>[FAIL]</error>';
            $this->line(sprintf('%s %s: %s', $prefix, $labels[$name] ?? Str::headline($name), $check['message']));
        }

        $this->newLine();

        if ($hasFailure) {
            $this->error('验证完成，存在失败项。');

            return self::FAILURE;
        }

        $this->info('验证完成，全部通过。');

        return self::SUCCESS;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkDatabase(string $connection): array
    {
        try {
            DB::connection($connection)->selectOne('select 1 as ok');

            return [
                'ok' => true,
                'message' => "connection [{$connection}] reachable",
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function resolveDatabaseConnectionName(string $connection): string
    {
        $connection = mb_trim($connection);

        if ($connection === '' || $connection === 'default') {
            return (string) config('database.default', 'default');
        }

        return $connection;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkRedis(string $connection): array
    {
        try {
            $pong = Redis::connection($connection)->ping();

            return [
                'ok' => true,
                'message' => "connection [{$connection}] ping response: ".$this->stringifyValue($pong),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkScoutMeilisearch(): array
    {
        $driver = (string) config('scout.driver', 'collection');

        if ($driver !== 'meilisearch') {
            return [
                'ok' => false,
                'message' => "scout driver [{$driver}] is not meilisearch",
            ];
        }

        try {
            $health = app(MeilisearchClient::class)->health();
            $status = is_array($health) ? (string) ($health['status'] ?? '') : '';

            if ($status !== 'available') {
                return [
                    'ok' => false,
                    'message' => sprintf(
                        'meilisearch health returned %s',
                        $this->stringifyValue($health),
                    ),
                ];
            }

            return [
                'ok' => true,
                'message' => sprintf(
                    'scout driver [%s] meilisearch health: %s',
                    $driver,
                    $this->stringifyValue($health),
                ),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkGarage(string $disk): array
    {
        if (! config("filesystems.disks.{$disk}")) {
            return [
                'ok' => false,
                'message' => "disk [{$disk}] is not configured",
            ];
        }

        try {
            Storage::disk($disk)->directories('');

            return [
                'ok' => true,
                'message' => "disk [{$disk}] reachable",
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkQueue(): array
    {
        $connectionName = (string) config('queue.default', 'sync');
        $connectionConfig = (array) config("queue.connections.{$connectionName}", []);
        $driver = (string) ($connectionConfig['driver'] ?? $connectionName);

        if ($driver === 'sync') {
            return [
                'ok' => true,
                'message' => "connection [{$connectionName}] uses sync driver",
            ];
        }

        $queueName = (string) ($connectionConfig['queue'] ?? 'default');

        try {
            $size = Queue::connection($connectionName)->size($queueName);

            return [
                'ok' => true,
                'message' => "connection [{$connectionName}] queue [{$queueName}] reachable, pending jobs: {$size}",
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * 通过 Redis 中的 master-supervisors set 判断 Horizon 进程是否存活。
     * 仅当 queue.default driver 为 redis 时才有意义；非 redis driver 时跳过。
     *
     * @return array{ok: bool, message: string}
     */
    private function checkHorizon(): array
    {
        $connectionName = (string) config('queue.default', 'sync');
        $connectionConfig = (array) config("queue.connections.{$connectionName}", []);
        $driver = (string) ($connectionConfig['driver'] ?? $connectionName);

        // Horizon 仅适用于 redis driver
        if ($driver !== 'redis') {
            return [
                'ok' => true,
                'message' => "skipped (queue driver is [{$driver}], not redis)",
            ];
        }

        try {
            $redisConnection = (string) config('horizon.use', 'default');
            $prefix = rtrim((string) config('horizon.prefix', 'laravel_horizon:'), ':');

            /** @var string[] $supervisors */
            $supervisors = Redis::connection($redisConnection)->smembers("{$prefix}:master-supervisors");

            if ($supervisors === []) {
                return [
                    'ok' => true,
                    'message' => 'not running (no master supervisor found)',
                ];
            }

            return [
                'ok' => true,
                'message' => 'running, supervisors: ['.implode(', ', $supervisors).']',
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkCentrifugo(): array
    {
        try {
            /** @var Centrifugo $centrifugo */
            $centrifugo = app('centrifugo');
            /** @var array<string, mixed> $info */
            $info = $centrifugo->info();

            if (array_key_exists('error', $info) && $info['error'] !== null) {
                return [
                    'ok' => false,
                    'message' => 'Centrifugo error: '.$info['error'],
                ];
            }

            return [
                'ok' => true,
                'message' => 'Centrifugo reachable',
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @throws JsonException
     */
    private function stringifyValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded !== false ? $encoded : '[]';
        }

        return (string) $value;
    }
}
