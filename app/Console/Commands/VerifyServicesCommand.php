<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Meilisearch\Client as MeilisearchClient;
use Throwable;

#[Signature('app:verify-services {--database=default : 数据库连接名} {--redis=default : Redis 连接名} {--disk=garage : 存储磁盘}')]
#[Description('验证 Redis、数据库、Scout(Meilisearch)、Garage 和队列是否可用')]
class VerifyServicesCommand extends Command
{
    public function handle(): int
    {
        $checks = [
            'database' => $this->checkDatabase($this->resolveDatabaseConnectionName((string) $this->option('database'))),
            'redis' => $this->checkRedis((string) $this->option('redis')),
            'scout' => $this->checkScoutMeilisearch(),
            'garage' => $this->checkGarage((string) $this->option('disk')),
            'queue' => $this->checkQueue(),
        ];

        $labels = [
            'database' => 'Database',
            'redis' => 'Redis',
            'scout' => 'Scout',
            'garage' => 'disk',
            'queue' => 'Queue',
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
                'message' => "connection [{$connection}] ping response: " . $this->stringifyValue($pong),
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
     * @throws \JsonException
     */
    private function stringifyValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    }
}
