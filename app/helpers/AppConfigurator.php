<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use PDOException;
use Spatie\ResponseCache\Middlewares\CacheResponse;
use Spatie\ResponseCache\Middlewares\DoNotCacheResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Throwable;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

/**
 * 应用配置助手类
 * 提供路由、中间件、异常处理等配置功能
 */
class AppConfigurator
{
    private const string API_PREFIX = 'api';

    private const string API_V1_PREFIX = 'api/v1';

    private const string API_V2_PREFIX = 'api/v2';

    /**
     * 配置API路由
     * 支持版本化路由和默认路由配置
     */
    public static function configureRoutes(): void
    {
        // 定义路由文件映射
        $routeFiles = self::getRouteFiles();

        // 配置v1版本路由
        self::configureV1Routes($routeFiles['v1']);

        // 配置v1版本管理员路由
        self::configureV1AdminRoutes($routeFiles['v1_admin']);

        // 配置v2版本管理员路由
        self::configureV2AdminRoutes($routeFiles['v2_admin']);

        // 配置默认API路由
        self::configureDefaultRoutes($routeFiles['default']);

        //        RateLimiter::for('api', function (Request $request) {
        //            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        //        });
    }

    /**
     * 获取路由文件配置
     */
    private static function getRouteFiles(): array
    {
        return [
            'v1' => [
                base_path('routes/api/v1/api.php'),
            ],
            'v1_admin' => [
                base_path('routes/api/v1/admin.php'),
            ],
            'v1_customer' => [
                base_path('routes/api/v1/customer.php'),
            ],
            'default' => [
                base_path('routes/api/default.php'),
            ],
            'api' => [
                base_path('routes/api.php'),
            ],
            'v2_admin' => [
                base_path('routes/api/v2/admin.php'),
            ],

        ];
    }

    /**
     * 配置v1版本路由
     */
    private static function configureV1Routes(array $routes): void
    {
        Route::middleware('api')
            ->prefix(self::API_V1_PREFIX)
            ->name('v1.')
            ->group($routes);
    }

    /**
     * 配置v1版本管理员路由
     */
    private static function configureV1AdminRoutes(array $routes): void
    {
        Route::middleware(['api', 'auth:admin'])
            ->prefix(self::API_V1_PREFIX.'/admin')
            ->name('v1.admin.')
            ->group($routes);
    }

    /**
     * 配置v2版本管理员路由
     */
    private static function configureV2AdminRoutes(array $routes): void
    {
        Route::middleware(['api', 'auth:admin'])
            ->prefix(self::API_V2_PREFIX.'/admin')
            ->name('v2.admin.')
            ->group($routes);
    }

    /**
     * 配置默认API路由
     */
    private static function configureDefaultRoutes(array $routes): void
    {
        Route::middleware('api')
            ->prefix(self::API_PREFIX)
            ->group($routes);
    }

    /**
     * 配置中间件
     * 支持全局中间件、路由别名和分组中间件配置
     */
    public static function configureMiddleware(Middleware $middleware): void
    {
        // 强制所有未认证的请求返回 401，不进行重定向
        // 这样当用户未登录访问受限接口时，会直接收到 401 错误码
        $middleware->redirectGuestsTo(fn () => null);

        // 注册全局中间件
        self::registerGlobalMiddleware($middleware);

        // 注册路由中间件别名
        self::registerMiddlewareAliases($middleware);

        // 为特定路由组添加中间件
        self::registerGroupMiddleware($middleware);

        $middleware->preventRequestForgery(except: [
            'wechat',
            'api/v1/stripe/webhook',
        ]);

        // 移除 API 组的频率限制（如果你在压测时不需要它）
        $middleware->api(remove: [
            ThrottleRequests::class,
        ]);

        // 或者替换某个中间件
        //        $middleware->web(replace: [
        //            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class => \App\Http\Middleware\MyCustomCsrf::class,
        //        ]);
    }

    /**
     * 注册全局中间件
     */
    private static function registerGlobalMiddleware(Middleware $middleware): void
    {
        // 可根据需要启用
        // $middleware->append(\App\Http\Middleware\TrustProxies::class);
        // $middleware->append(\App\Http\Middleware\HandleCors::class);

        $middleware->web(append: [
            CacheResponse::class,
        ]);
    }

    /**
     * 注册中间件别名
     */
    private static function registerMiddlewareAliases(Middleware $middleware): void
    {
        $middleware->alias([
            //             'auth' => \App\Http\Middleware\Authenticate::class,
            //             'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            //             'admin' => \App\Http\Middleware\CheckAdminRole::class,
            'throttle' => ThrottleRequests::class,
            'doNotCacheResponse' => DoNotCacheResponse::class,
            'cacheResponse' => CacheResponse::class,
            'jwt.auth_version' => \App\Http\Middleware\EnsureJwtAuthVersionIsCurrent::class,
        ]);
    }

    /**
     * 注册分组中间件
     */
    private static function registerGroupMiddleware(Middleware $middleware): void
    {
        // 可根据需要启用
        // $middleware->group('api', [
        //     \App\Http\Middleware\LogApiRequests::class,
        //     \App\Http\Middleware\HandleApiResponse::class,
        // ]);
    }

    /**
     * 配置任务调度
     * 提供常用的定时任务配置模板
     */
    public static function configureSchedule(): void
    {
        // 系统维护任务
        self::configureMaintenanceTasks();

        // 数据处理任务
        self::configureDataTasks();

        // 监控和日志任务
        self::configureMonitoringTasks();
    }

    /**
     * 配置系统维护任务
     */
    private static function configureMaintenanceTasks(): void
    {
        // 数据库备份任务
        Schedule::command('backup:run --only-db')
            ->daily()
            ->at('02:00')
            ->sendOutputTo(storage_path('logs/backup.log'))
            ->timezone('Asia/Shanghai')
            ->onFailure(function (): void {
                // 备份失败通知逻辑
            });
        //        Schedule::command(ScheduleCheckHeartbeatCommand::class)->everyFiveMinutes();
        Schedule::command('backup:clean')->daily()->at('01:00');
    }

    /**
     * 配置数据处理任务
     */
    private static function configureDataTasks(): void
    {
        // 数据同步任务
        // Schedule::command('data:sync')
        //     ->hourly()
        //     ->withoutOverlapping();

        // 统计数据生成
        // Schedule::command('statistics:generate')
        //     ->daily()
        //     ->at('05:00');

        //        // 每分钟检查设备状态并发送告警
        //        Schedule::call(static function () {
        //            $monitorService = new DeviceMonitorService();
        //            $monitorService->sendOfflineAlerts();
        //        })->everyMinute();
        //
        //        // 每5分钟记录设备状态统计
        //        Schedule::call(static function () {
        //            $monitorService = new DeviceMonitorService();
        //            $stats = $monitorService->getDeviceStats();
        //            Log::info('设备状态统计', $stats);
        //        })->everyFiveMinutes();
        Schedule::command('orders:cleanup-expired')->daily()->at('22:30');
        Schedule::command('device-failure-logs:cleanup --days=1 -f')->at('01:00');
        Schedule::command('scribe:generate')->daily()->at('04:00');

        // 每年6月30日凌晨2点清空所有用户积分
        //        Schedule::command('integral:clear --force')
        //            ->yearlyOn(6, 30, '11:59')
        //            ->timezone('Asia/Shanghai')
        //            ->sendOutputTo(storage_path('logs/integral-clear.log'))
        //            ->onSuccess(function () {
        //                Log::info('积分清空任务执行成功 - 6月30日');
        //            })
        //            ->onFailure(function () {
        //                Log::error('积分清空任务执行失败 - 6月30日');
        //            });
        //
        //        // 每年12月31日凌晨2点清空所有用户积分
        //        Schedule::command('integral:clear --force')
        //            ->yearlyOn(12, 31, '11:59')
        //            ->timezone('Asia/Shanghai')
        //            ->sendOutputTo(storage_path('logs/integral-clear.log'))
        //            ->onSuccess(function () {
        //                Log::info('积分清空任务执行成功 - 12月31日');
        //            })
        //            ->onFailure(function () {
        //                Log::error('积分清空任务执行失败 - 12月31日');
        //            });
    }

    /**
     * 配置监控和日志任务
     */
    private static function configureMonitoringTasks(): void
    {
        // 清理Telescope日志
        // Schedule::command('telescope:prune --hours=48')->daily()->at('03:00');
        // 每小时清理一次临时缓存数据
        //        Schedule::command('pulse:clear')->hourly();
        // 自定义命令：删除90天前的Pulse历史数据
        //        Schedule::command('pulse:purge')->dailyAt('01:00');

        // Horizon 快照
        Schedule::command('horizon:snapshot')->everyFiveMinutes();
    }

    public static function configureLogColorStderr(): void
    {

        Log::extend('color_stderr', function () {
            $handler = new StreamHandler('php://stderr');

            // 自定义格式化器，根据日志级别动态改变颜色
            $formatter = new class () extends LineFormatter {
                // 定义不同级别的颜色代码
                private array $levelColors = [
                    'DEBUG' => '34',    // 蓝色
                    'INFO' => '32',     // 绿色
                    'WARNING' => '33',  // 黄色
                    'ERROR' => '31',    // 红色
                    'CRITICAL' => '35', // 紫红色
                    'ALERT' => '36',    // 青色
                    'EMERGENCY' => '1;31', // 加粗红色
                ];

                public function format(LogRecord $record): string
                {
                    // 获取当前日志级别的颜色
                    $colorCode = $this->levelColors[mb_strtoupper($record->level->getName())] ?? '37';

                    // 构建带颜色的格式
                    $format = "\033[32m%s\033[0m \033[{$colorCode}m%s\033[0m: %s %s\n";

                    // 格式化输出
                    return sprintf(
                        $format,
                        $record->datetime->format('Y-m-d H:i:s'),
                        mb_strtoupper($record->level->getName()),
                        $record->message,
                        empty($record->context) ? '' : json_encode($record->context, JSON_THROW_ON_ERROR),
                    );
                }
            };

            $handler->setFormatter($formatter);

            return new Logger('color_stderr', [$handler]);
        });
    }

    /**
     * 配置异常处理
     * 统一处理API和Web请求的异常响应
     */
    public static function configureExceptions(Exceptions $exceptions): void
    {
        // 配置API异常处理
        self::configureApiExceptions($exceptions);

        // 配置Web异常处理
        self::configureWebExceptions($exceptions);

        // 配置限流异常处理
        self::configureThrottleExceptions($exceptions);

        // 配置监控集成
        //        self::configureSentryIntegration($exceptions);

    }

    /**
     * 配置API异常处理
     */
    private static function configureApiExceptions(Exceptions $exceptions): void
    {
        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->is(self::API_PREFIX.'/*')) {
                return self::renderApiException($e);
            }
        });
    }

    /**
     * 处理API请求的异常响应
     * 统一格式化API异常返回结构
     */
    private static function renderApiException(Throwable $e): JsonResponse
    {
        // 获取HTTP状态码
        $statusCode = self::getHttpStatusCode($e);

        // 构建基础响应结构
        $response = self::buildBaseResponse($e, $statusCode);

        // 添加调试信息（仅本地环境）
        //        if (app()->isLocal()) {
        //            $response['debug'] = self::getDebugInfo($e);
        //        }

        if (config('app.debug')) {
            $response['debug'] = self::getDebugInfo($e);
        }

        // 根据异常类型定制响应
        [$statusCode, $response] = self::customizeExceptionResponse($e, $statusCode, $response);

        return response()->json($response, $statusCode);
    }

    /**
     * 获取HTTP状态码
     */
    private static function getHttpStatusCode(Throwable $e): int
    {
        // Excel 验证异常返回 422
        if ($e instanceof ExcelValidationException) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        // Laravel 验证异常返回 422
        if ($e instanceof ValidationException) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        // HTTP 异常返回对应状态码
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        // 其他异常返回 500
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    /**
     * 构建基础响应结构
     */
    private static function buildBaseResponse(Throwable $e, int $statusCode): array
    {
        $response = [
            'success' => false,
            'code' => $statusCode,
            'message' => self::getExceptionMessage($e, $statusCode),
        ];

        // 添加 errors 字段（如果存在）
        $errors = self::extractErrors($e);
        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        $response['exception'] = class_basename($e);
        $response['timestamp'] = now()->toIso8601String();

        return $response;
    }

    /**
     * 从异常中提取错误信息
     * 根据异常类型提取相应的错误详情
     */
    private static function extractErrors(Throwable $e): array
    {
        $errors = [];

        // Excel 验证异常
        if ($e instanceof ExcelValidationException) {
            $failures = $e->failures();
            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }
        }

        // Laravel 验证异常
        if ($e instanceof ValidationException) {
            $errors = $e->errors();
        }

        return $errors;
    }

    /**
     * 获取异常信息
     * 根据环境和状态码返回合适的错误消息
     */
    private static function getExceptionMessage(Throwable $e, int $statusCode): string
    {
        // 生产环境下的500错误使用Laravel标准状态文本
        if ($statusCode === Response::HTTP_INTERNAL_SERVER_ERROR && ! app()->isLocal()) {
            return Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR] ?? 'Internal Server Error';
        }

        // 返回异常消息或Laravel标准状态文本
        return $e->getMessage() ?: (Response::$statusTexts[$statusCode] ?? 'Unknown Error');
    }

    /**
     * 获取调试信息
     * 提供结构化的异常调试信息
     */
    private static function getDebugInfo(Throwable $e): array
    {
        return [
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => self::formatTraceAsJson($e->getTrace()),
            'previous' => $e->getPrevious() ? [
                'message' => $e->getPrevious()->getMessage(),
                'file' => $e->getPrevious()->getFile(),
                'line' => $e->getPrevious()->getLine(),
            ] : null,
        ];
    }

    /**
     * 将异常堆栈跟踪格式化为结构化的JSON数组
     * 提供更清晰的调试信息展示
     */
    private static function formatTraceAsJson(array $trace): array
    {
        $formattedTrace = [];

        foreach ($trace as $index => $item) {
            $traceItem = [
                'step' => $index + 1,
                'file' => $item['file'] ?? 'unknown',
                'line' => $item['line'] ?? 0,
                'function' => $item['function'] ?? 'unknown',
            ];

            // 添加类信息（如果存在）
            if (isset($item['class'])) {
                $traceItem['class'] = $item['class'];
                $traceItem['type'] = $item['type'] ?? '->';
            }

            // 添加参数信息（仅在本地环境显示，避免敏感信息泄露）
            if (app()->isLocal() && isset($item['args']) && ! empty($item['args'])) {
                $traceItem['args'] = self::formatTraceArgs($item['args']);
            }

            $formattedTrace[] = $traceItem;
        }

        return $formattedTrace;
    }

    /**
     * 格式化堆栈跟踪参数
     * 安全地处理参数信息，避免敏感数据泄露
     */
    private static function formatTraceArgs(array $args): array
    {
        $formattedArgs = [];

        foreach ($args as $index => $arg) {
            if (is_object($arg)) {
                $formattedArgs[$index] = [
                    'type' => 'object',
                    'class' => get_class($arg),
                ];
            } elseif (is_array($arg)) {
                $formattedArgs[$index] = [
                    'type' => 'array',
                    'count' => count($arg),
                ];
            } elseif (is_string($arg)) {
                // 限制字符串长度，避免过长的参数
                $formattedArgs[$index] = [
                    'type' => 'string',
                    'value' => mb_strlen($arg) > 100 ? mb_substr($arg, 0, 100).'...' : $arg,
                ];
            } elseif (is_numeric($arg)) {
                $formattedArgs[$index] = [
                    'type' => is_int($arg) ? 'integer' : 'float',
                    'value' => $arg,
                ];
            } elseif (is_bool($arg)) {
                $formattedArgs[$index] = [
                    'type' => 'boolean',
                    'value' => $arg,
                ];
            } elseif (is_null($arg)) {
                $formattedArgs[$index] = [
                    'type' => 'null',
                    'value' => null,
                ];
            } else {
                $formattedArgs[$index] = [
                    'type' => gettype($arg),
                    'value' => 'unknown',
                ];
            }
        }

        return $formattedArgs;
    }

    /**
     * 根据异常类型定制响应
     */
    private static function customizeExceptionResponse(Throwable $e, int $statusCode, array $response): array
    {
        // Excel验证异常
        if ($e instanceof ExcelValidationException) {
            return self::handleExcelValidationException($e, $response);
        }

        // 验证异常
        if ($e instanceof ValidationException) {
            return self::handleValidationException($e, $response);
        }

        // 自定义API异常
        if ($e instanceof ApiException) {
            return self::handleApiException($e, $response);
        }

        // JWT相关异常
        if (self::isJwtException($e)) {
            return self::handleJwtException($e, $response);
        }

        // 认证异常
        if ($e instanceof AuthenticationException) {
            return self::handleAuthenticationException($e, $response);
        }

        // 授权异常
        if ($e instanceof UnauthorizedHttpException) {
            return self::handleUnauthorizedException($e, $response);
        }

        // 404相关异常
        if (self::isNotFoundException($e)) {
            return self::handleNotFoundException($e, $response);
        }

        // 其他HTTP异常
        if ($e instanceof HttpExceptionInterface) {
            return self::handleHttpException($e, $statusCode, $response);
        }

        // 数据库异常
        if (self::isDatabaseException($e)) {
            return self::handleDatabaseException($e, $response);
        }

        return [$statusCode, $response];
    }

    /**
     * 处理Excel验证异常
     * 统一处理Excel导入过程中的数据验证异常
     */
    private static function handleExcelValidationException(ExcelValidationException $e, array $response): array
    {
        $failures = $e->failures();
        $errorMessages = [];

        foreach ($failures as $failure) {
            $errorMessages[] = [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
                'values' => $failure->values(),
            ];
        }

        $response['code'] = Response::HTTP_UNPROCESSABLE_ENTITY;
        $response['message'] = 'Data validation failed, please check the Excel file and data';
        $response['errors'] = $errorMessages;

        return [Response::HTTP_UNPROCESSABLE_ENTITY, $response];
    }

    /**
     * 处理验证异常
     */
    private static function handleValidationException(ValidationException $e, array $response): array
    {
        $response['code'] = Response::HTTP_UNPROCESSABLE_ENTITY;
        $response['message'] = Response::$statusTexts[Response::HTTP_UNPROCESSABLE_ENTITY] ?? 'Unprocessable Entity';
        $response['errors'] = $e->errors();

        return [Response::HTTP_UNPROCESSABLE_ENTITY, $response];
    }

    /**
     * 处理自定义API异常
     */
    private static function handleApiException(ApiException $e, array $response): array
    {
        $statusCode = $e->getCode();
        $response['code'] = $e->getCustomCode() ?? Response::HTTP_BAD_REQUEST;
        $response['message'] = $e->getMessage();

        if ($e->getErrors()) {
            $response['errors'] = $e->getErrors();
        }

        return [$statusCode, $response];
    }

    /**
     * 检查是否为JWT异常
     */
    private static function isJwtException(Throwable $e): bool
    {
        return $e instanceof TokenInvalidException
            || $e instanceof TokenExpiredException;
    }

    /**
     * 处理JWT异常
     */
    private static function handleJwtException(Throwable $e, array $response): array
    {
        $message = $e instanceof TokenInvalidException
            ? 'Token was invalid'
            : 'Token was expired';

        $response['code'] = Response::HTTP_UNAUTHORIZED;
        $response['message'] = $message;

        return [Response::HTTP_UNAUTHORIZED, $response];
    }

    /**
     * 处理认证异常
     */
    private static function handleAuthenticationException(AuthenticationException $e, array $response): array
    {
        $response['code'] = Response::HTTP_UNAUTHORIZED;
        $response['message'] = Response::$statusTexts[Response::HTTP_UNAUTHORIZED] ?? 'Unauthorized';

        return [Response::HTTP_UNAUTHORIZED, $response];
    }

    /**
     * 处理授权异常
     */
    private static function handleUnauthorizedException(UnauthorizedHttpException $e, array $response): array
    {
        $statusCode = $e->getStatusCode();
        $response['code'] = $statusCode;
        $response['message'] = $e->getMessage() ?: (Response::$statusTexts[$statusCode] ?? 'Unauthorized');

        return [$statusCode, $response];
    }

    /**
     * 检查是否为数据库异常
     */
    private static function isDatabaseException(Throwable $e): bool
    {
        return $e instanceof PDOException;
    }

    /**
     * 处理数据库异常
     */
    private static function handleDatabaseException(Throwable $e, array $response): array
    {
        $response['code'] = Response::HTTP_INTERNAL_SERVER_ERROR;
        if ($e instanceof UniqueConstraintViolationException) {
            $response['message'] = config('app.debug')
                ? 'Database query error: '.$e->getMessage()
                : 'Database operation failed, please try again later';
        } elseif ($e instanceof QueryException) {
            $response['message'] = config('app.debug')
                ? 'Database query error: '.$e->getMessage()
                : 'Database operation failed, please try again later';
        } elseif ($e instanceof PDOException) {
            $response['message'] = config('app.debug')
                ? 'Database connection error: '.$e->getMessage()
                : 'Database operation failed, please try again later';
        } else {
            $response['message'] = config('app.debug')
                ? 'Database error: '.$e->getMessage()
                : 'Database operation failed, please try again later';
        }

        return [$response['code'], $response];
    }

    /**
     * 检查是否为404异常
     */
    private static function isNotFoundException(Throwable $e): bool
    {
        return $e instanceof NotFoundResourceException
            || $e instanceof ModelNotFoundException
            || $e instanceof NotFoundHttpException;
    }

    /**
     * 处理404异常
     */
    private static function handleNotFoundException(Throwable $e, array $response): array
    {
        $response['code'] = Response::HTTP_NOT_FOUND;

        if ($e instanceof NotFoundResourceException) {
            $response['message'] = 'Resource not found';
        } elseif ($e instanceof ModelNotFoundException) {
            $response['message'] = 'Data not found';
        } elseif ($e instanceof NotFoundHttpException) {
            $response['message'] = 'Route or Resource not found';
        } else {
            $response['message'] = $e->getMessage() ?: (Response::$statusTexts[Response::HTTP_NOT_FOUND] ?? 'Not Found');
        }

        return [Response::HTTP_NOT_FOUND, $response];
    }

    /**
     * 处理HTTP异常
     */
    private static function handleHttpException(HttpExceptionInterface $e, int $statusCode, array $response): array
    {
        $response['code'] = $statusCode;
        $response['message'] = Response::$statusTexts[$statusCode] ?? $response['message'];

        return [$statusCode, $response];
    }

    /**
     * 配置Web异常处理
     */
    private static function configureWebExceptions(Exceptions $exceptions): void
    {
        // Web异常处理应该让Laravel默认处理，不强制返回JSON
        // 只有API路径才需要特殊的JSON异常处理
    }

    /**
     * 配置限流异常处理
     */
    private static function configureThrottleExceptions(Exceptions $exceptions): void
    {
        $exceptions->renderable(function (ThrottleRequestsException $e) {
            $response = [
                'success' => false,
                'code' => Response::HTTP_TOO_MANY_REQUESTS,
                'message' => Response::$statusTexts[Response::HTTP_TOO_MANY_REQUESTS] ?? 'Too Many Requests',
                'timestamp' => now()->format('Y-m-d h:i:s'),
            ];

            // 尝试从异常头部获取重试时间
            if (method_exists($e, 'getHeaders')) {
                $headers = $e->getHeaders();
                if (isset($headers['Retry-After'])) {
                    $response['retry_after'] = (int) $headers['Retry-After'];
                }
            }

            return response()->json($response, Response::HTTP_TOO_MANY_REQUESTS);
        });
    }

    /**
     * 配置Sentry监控集成
     */
    private static function configureSentryIntegration(Exceptions $exceptions): void
    {
        // 检查是否为生产环境和Sentry集成类是否存在
        //        if (config('app.env') === 'production' && class_exists(Integration::class)) {
        //            Integration::handles($exceptions);
        //        }
    }
}
