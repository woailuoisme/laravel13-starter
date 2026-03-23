<?php

declare(strict_types=1);

namespace App\Helpers;

use Closure;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

/**
 * 数据库查询日志监听器
 *
 * 在 debug 模式下监听所有 DB 查询，
 * 慢查询（> SLOW_QUERY_THRESHOLD ms）以 warning 级别记录，其余为 debug 级别。
 */
class DBSql
{
    /** 慢查询阈值（毫秒），超出该值将以 warning 级别记录 */
    private const int SLOW_QUERY_THRESHOLD = 100;

    /**
     * 注册数据库查询监听器（仅 debug 模式生效）
     */
    public function listen(): void
    {
        if (! config('app.debug')) {
            return;
        }

        DB::listen(function ($query): void {
            try {
                $logData = $this->logQuery(
                    $query->connectionName,
                    $query->sql,
                    $query->bindings,
                    $query->time,
                );

                $level = $logData['time'] > self::SLOW_QUERY_THRESHOLD ? 'warning' : 'debug';
                $message = "[DB] [{$logData['connection']}][{$logData['time']}ms][{$logData['query']}]";

                Log::channel('stderr')->log($level, $message);
            } catch (Throwable $e) {
                Log::channel('stderr')->error("数据库日志处理失败: {$e->getMessage()}");
            }
        });
    }

    /**
     * 格式化 SQL 查询并返回日志数据结构
     *
     * @param array<mixed> $bindings 绑定参数
     * @return array{connection: string, query: string, time: float}
     */
    public function logQuery(string $connection, string $query, array $bindings, float $time): array
    {
        if (! empty($bindings)) {
            $query = $this->replaceBindings($query, $bindings);
        }

        $query = mb_rtrim($query, ';').';';

        return compact('connection', 'query', 'time');
    }

    /**
     * 将 SQL 中的 ? 占位符替换为实际绑定值
     *
     * @param array<mixed> $bindings
     */
    private function replaceBindings(string $query, array $bindings): string
    {
        $formattedSql = str_replace(['%', '?'], ['%%', "'%s'"], $query);
        $normalizedBindings = array_map(fn (mixed $b): string => $this->bindingToString($b), $bindings);

        return vsprintf($formattedSql, $normalizedBindings);
    }

    /**
     * 将任意类型的绑定值安全转换为字符串
     */
    private function bindingToString(mixed $binding): string
    {
        try {
            if ($binding === null || is_scalar($binding)) {
                return (string) $binding;
            }

            if ($binding instanceof DateTimeImmutable) {
                return $binding->format('Y-m-d H:i:s');
            }

            if ($binding instanceof Closure) {
                return '[Closure]';
            }

            if (is_array($binding)) {
                return json_encode($binding, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }

            if (is_object($binding)) {
                return method_exists($binding, '__toString') ? (string) $binding : $binding::class;
            }

            return '[Unknown Type]';
        } catch (JsonException) {
            return '[Array (JSON encode failed)]';
        } catch (Throwable) {
            return '[Conversion Error]';
        }
    }
}
