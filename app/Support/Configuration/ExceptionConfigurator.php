<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use App\Exceptions\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Throwable;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

/**
 * 应用异常处理配置类
 * 统一处理API和Web请求的异常响应
 */
class ExceptionConfigurator
{
    private const string API_PREFIX = 'api';

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
        $exceptions->renderable(static function (Throwable $e, Request $request) {
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
        $customized = self::customizeExceptionResponse($e, $statusCode, $response);
        $finalStatusCode = $customized[0];
        $finalResponse = $customized[1];

        return response()->json($finalResponse, $finalStatusCode);
    }

    /**
     * 获取HTTP状态码
     */
    private static function getHttpStatusCode(Throwable $e): int
    {
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
     *
     * @return array<string, mixed>
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
        if ($errors !== []) {
            $response['errors'] = $errors;
        }

        $response['exception'] = class_basename($e);
        $response['timestamp'] = now()->toIso8601String();

        return $response;
    }

    /**
     * 从异常中提取错误信息
     * 根据异常类型提取相应的错误详情
     *
     * @return array<array-key, mixed>
     */
    private static function extractErrors(Throwable $e): array
    {
        $errors = [];

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

        $msg = $e->getMessage();

        // 返回异常消息或Laravel标准状态文本
        return $msg !== '' ? $msg : Response::$statusTexts[$statusCode] ?? 'Unknown Error';
    }

    /**
     * 获取异常调试信息
     * 提供结构化的异常调试信息
     *
     * @return array{line: int, file: string, trace: list<array<string, mixed>>, previous: ?array{message: string, file: string, line: int}}
     */
    private static function getDebugInfo(Throwable $e): array
    {
        $previous = $e->getPrevious();
        /** @var list<array<string, mixed>> $rawTrace */
        $rawTrace = $e->getTrace();

        return [
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => self::formatTraceAsJson($rawTrace),
            'previous' => $previous !== null
                ? [
                    'message' => $previous->getMessage(),
                    'file' => $previous->getFile(),
                    'line' => $previous->getLine(),
                ] : null,
        ];
    }

    /**
     * 将异常堆栈跟踪格式化为结构化的JSON数组
     * 提供更清晰的调试信息展示
     *
     * @param  list<array<string, mixed>>  $trace
     * @return list<array<string, mixed>>
     */
    private static function formatTraceAsJson(array $trace): array
    {
        $formattedTrace = [];
        $step = 1;

        foreach ($trace as $item) {
            $traceItem = [
                'step' => $step++,
                'file' => is_string($item['file'] ?? null) ? $item['file'] : 'unknown',
                'line' => is_int($item['line'] ?? null) ? $item['line'] : 0,
                'function' => is_string($item['function'] ?? null) ? $item['function'] : 'unknown',
            ];

            // 添加类信息（如果存在）
            if (is_string($item['class'] ?? null)) {
                $traceItem['class'] = $item['class'];
                $traceItem['type'] = is_string($item['type'] ?? null) ? $item['type'] : '->';
            }

            // 添加参数信息（仅在本地环境显示，避免敏感信息泄露）
            if (
                app()->isLocal()
                && is_array($item['args'] ?? null)
                && $item['args'] !== []
            ) {
                /** @var list<mixed> $args */
                $args = $item['args'];
                $traceItem['args'] = self::formatTraceArgs($args);
            }

            $formattedTrace[] = $traceItem;
        }

        return $formattedTrace;
    }

    /**
     * 格式化堆栈跟踪参数
     * 安全地处理参数信息，避免敏感数据泄露
     *
     * @param  list<mixed>  $args
     * @return list<array<string, mixed>>
     */
    private static function formatTraceArgs(array $args): array
    {
        return array_map(static fn (mixed $arg): array => match (true) {
            is_object($arg) => [
                'type' => 'object',
                'class' => get_class($arg),
            ],
            is_array($arg) => [
                'type' => 'array',
                'count' => count($arg),
            ],
            is_string($arg) => [
                'type' => 'string',
                'value' => Str::limit($arg, 100),
            ],
            is_int($arg) => [
                'type' => 'integer',
                'value' => $arg,
            ],
            is_float($arg) => [
                'type' => 'float',
                'value' => $arg,
            ],
            is_bool($arg) => [
                'type' => 'boolean',
                'value' => $arg,
            ],
            is_null($arg) => [
                'type' => 'null',
                'value' => null,
            ],
            default => [
                'type' => gettype($arg),
                'value' => 'unknown',
            ],
        }, $args);
    }

    /**
     * 根据异常类型定制响应
     *
     * @param  array<string, mixed>  $response
     * @return array{0: int, 1: array<string, mixed>}
     */
    private static function customizeExceptionResponse(Throwable $e, int $statusCode, array $response): array
    {
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
     * 处理验证异常
     *
     * @param  array<string, mixed>  $response
     * @return array{0: int, 1: array<string, mixed>}
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
     *
     * @param  array<string, mixed>  $response
     * @return array{0: int, 1: array<string, mixed>}
     */
    private static function handleApiException(ApiException $e, array $response): array
    {
        $rawCode = $e->getCode();
        $statusCode = is_int($rawCode) && $rawCode >= 100 && $rawCode <= 599 ? $rawCode : Response::HTTP_BAD_REQUEST;
        $response['code'] = $e->getCustomCode();
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
        return $e instanceof TokenInvalidException || $e instanceof TokenExpiredException;
    }

    /**
     * 处理JWT异常
     *
     * @param  array<string, mixed>  $response
     * @return array{0: int, 1: array<string, mixed>}
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
     *
     * @param  array<string, mixed>  $response
     * @return array{0: int, 1: array<string, mixed>}
     */
    private static function handleAuthenticationException(AuthenticationException $e, array $response): array
    {
        $response['code'] = Response::HTTP_UNAUTHORIZED;
        $response['message'] = Response::$statusTexts[Response::HTTP_UNAUTHORIZED] ?? 'Unauthorized';

        return [Response::HTTP_UNAUTHORIZED, $response];
    }

    /**
     * 处理授权异常
     *
     * @param  array<string, mixed>  $response
     * @return array{0: int, 1: array<string, mixed>}
     */
    private static function handleUnauthorizedException(UnauthorizedHttpException $e, array $response): array
    {
        $statusCode = $e->getStatusCode();
        $msg = $e->getMessage();
        $response['code'] = $statusCode;
        $response['message'] = $msg !== '' ? $msg : Response::$statusTexts[$statusCode] ?? 'Unauthorized';

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
     *
     * @param  array<string, mixed>  $response
     * @return array{0: int, 1: array<string, mixed>}
     */
    private static function handleDatabaseException(Throwable $e, array $response): array
    {
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $response['code'] = $statusCode;
        $isDbDebug = config()->boolean('app.debug');

        $detailMsg = match (true) {
            $e instanceof UniqueConstraintViolationException => 'Database query error: '.$e->getMessage(),
            $e instanceof QueryException => 'Database query error: '.$e->getMessage(),
            $e instanceof PDOException => 'Database connection error: '.$e->getMessage(),
            default => 'Database error: '.$e->getMessage(),
        };

        $response['message'] = $isDbDebug ? $detailMsg : 'Database operation failed, please try again later';

        return [$statusCode, $response];
    }

    /**
     * 检查是否为404异常
     */
    private static function isNotFoundException(Throwable $e): bool
    {
        return (
            $e instanceof NotFoundResourceException
            || $e instanceof ModelNotFoundException
            || $e instanceof NotFoundHttpException
        );
    }

    /**
     * 处理404异常
     *
     * @param  array<string, mixed>  $response
     * @return array{0: int, 1: array<string, mixed>}
     */
    private static function handleNotFoundException(Throwable $e, array $response): array
    {
        $response['code'] = Response::HTTP_NOT_FOUND;
        $msg = $e->getMessage();
        $defaultNotFound = $msg !== '' ? $msg : Response::$statusTexts[Response::HTTP_NOT_FOUND] ?? 'Not Found';

        $response['message'] = match (true) {
            $e instanceof NotFoundResourceException => 'Resource not found',
            $e instanceof ModelNotFoundException => 'Data not found',
            $e instanceof NotFoundHttpException => 'Route or Resource not found',
            default => $defaultNotFound,
        };

        return [Response::HTTP_NOT_FOUND, $response];
    }

    /**
     * 处理HTTP异常
     *
     * @param  array<string, mixed>  $response
     * @return array{0: int, 1: array<string, mixed>}
     */
    private static function handleHttpException(HttpExceptionInterface $e, int $statusCode, array $response): array
    {
        $msg = $e->getMessage();
        $response['code'] = $statusCode;
        $response['message'] = $msg !== ''
            ? $msg
            : Response::$statusTexts[$statusCode] ?? (string) ($response['message'] ?? '');

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
        $exceptions->renderable(static function (ThrottleRequestsException $e) {
            $response = [
                'success' => false,
                'code' => Response::HTTP_TOO_MANY_REQUESTS,
                'message' => Response::$statusTexts[Response::HTTP_TOO_MANY_REQUESTS] ?? 'Too Many Requests',
                'timestamp' => now()->format('Y-m-d h:i:s'),
            ];

            // 尝试从异常头部获取重试时间
            if (method_exists($e, 'getHeaders')) {
                $headers = $e->getHeaders();
                if (array_key_exists('Retry-After', $headers) && $headers['Retry-After'] !== null) {
                    $response['retry_after'] = (int) $headers['Retry-After'];
                }
            }

            return response()->json($response, Response::HTTP_TOO_MANY_REQUESTS);
        });
    }
}
