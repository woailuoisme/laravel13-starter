<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Support\Configuration\ExceptionConfigurator;
use App\Support\Configuration\MiddlewareConfigurator;
use App\Support\Configuration\RouteConfigurator;
use App\Support\Configuration\ScheduleConfigurator;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * 应用配置助手类
 * 提供路由、中间件、异常处理等配置功能
 */
class AppConfigurator
{
    /**
     * 配置API路由
     * 支持版本化路由和默认路由配置
     */
    public static function configureRoutes(): void
    {
        RouteConfigurator::configureRoutes();
    }

    /**
     * 配置中间件
     * 支持全局中间件、路由别名和分组中间件配置
     */
    public static function configureMiddleware(Middleware $middleware): void
    {
        MiddlewareConfigurator::configureMiddleware($middleware);
    }

    /**
     * 配置任务调度
     * 提供常用的定时任务配置模板
     */
    public static function configureSchedule(): void
    {
        ScheduleConfigurator::configureSchedule();
    }

    /**
     * 配置异常处理
     * 统一处理API和Web请求的异常响应
     */
    public static function configureExceptions(Exceptions $exceptions): void
    {
        ExceptionConfigurator::configureExceptions($exceptions);
    }

    public static function configureLogColorStderr(): void
    {
        Log::extend('color_stderr', static function () {
            $handler = new StreamHandler('php://stderr');

            // 自定义格式化器，根据日志级别动态改变颜色
            $formatter = new class extends LineFormatter {
                // 定义不同级别的颜色代码
                private array $levelColors = [
                    'DEBUG' => '34', // 蓝色
                    'INFO' => '32', // 绿色
                    'WARNING' => '33', // 黄色
                    'ERROR' => '31', // 红色
                    'CRITICAL' => '35', // 紫红色
                    'ALERT' => '36', // 青色
                    'EMERGENCY' => '1;31', // 加粗红色
                ];

                public function format(LogRecord $record): string
                {
                    $levelName = Str::upper($record->level->getName());
                    // 获取当前日志级别的颜色
                    $colorCode = $this->levelColors[$levelName] ?? '37';

                    // 构建带颜色的格式
                    $format = "\033[32m%s\033[0m \033[{$colorCode}m%s\033[0m: %s %s\n";

                    // 格式化输出
                    return sprintf(
                        $format,
                        $record->datetime->format('Y-m-d H:i:s'),
                        $levelName,
                        $record->message,
                        $record->context === [] ? '' : json_encode($record->context, JSON_THROW_ON_ERROR),
                    );
                }
            };

            $handler->setFormatter($formatter);

            return new Logger('color_stderr', [$handler]);
        });
    }
}
