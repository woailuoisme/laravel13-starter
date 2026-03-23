<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class BusinessException extends Exception
{
    protected ErrorCode $errorCode;

    protected array $context = [];

    public function __construct(
        ErrorCode $errorCode,
        ?string $message = null,
        array $context = [],
        ?Throwable $previous = null,
    ) {
        $this->errorCode = $errorCode;
        $this->context = $context;

        $defaultMessage = $errorCode->message();
        $businessCode = $errorCode->value;
        $finalMessage = $message ?? $defaultMessage;

        parent::__construct($finalMessage, $businessCode, $previous);
    }

    /**
     * 获取错误码枚举实例
     */
    public function getErrorCode(): ErrorCode
    {
        return $this->errorCode;
    }

    /**
     * 获取上下文数据
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 设置上下文数据
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * 记录异常日志
     */
    public function report(): void
    {
        $logContext = [
            'error_code' => $this->errorCode->value,
            'error_name' => $this->errorCode->name,
            'message' => $this->getMessage(),
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];

        // 根据 HTTP 状态码决定日志级别
        $httpStatus = $this->errorCode->httpStatus();
        if ($httpStatus >= 500) {
            Log::error('Business Exception', $logContext);
        } elseif ($httpStatus >= 400) {
            Log::warning('Business Exception', $logContext);
        } else {
            Log::info('Business Exception', $logContext);
        }
    }

    /**
     * 将异常渲染成统一格式的 JSON 响应
     */
    public function render(Request $request): JsonResponse
    {
        $response = [
            'success' => false,
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
        ];

        // 添加上下文数据（如果有）
        if (! empty($this->context)) {
            $response['data'] = $this->context;
        }

        // 在开发环境添加调试信息
        if (config('app.debug')) {
            $response['debug'] = [
                'error_code_name' => $this->errorCode->name,
                'file' => $this->getFile(),
                'line' => $this->getLine(),
                'trace' => collect($this->getTrace())
                    ->take(5)
                    ->map(fn ($trace) => [
                        'file' => $trace['file'] ?? 'unknown',
                        'line' => $trace['line'] ?? 0,
                        'function' => $trace['function'] ?? 'unknown',
                        'class' => $trace['class'] ?? null,
                    ])
                    ->toArray(),
            ];

            if ($this->getPrevious()) {
                $response['debug']['previous'] = [
                    'message' => $this->getPrevious()->getMessage(),
                    'file' => $this->getPrevious()->getFile(),
                    'line' => $this->getPrevious()->getLine(),
                ];
            }
        }

        return response()->json($response, $this->errorCode->httpStatus());
    }

    /**
     * 静态工厂方法：快速创建异常
     */
    public static function make(
        ErrorCode $errorCode,
        ?string $message = null,
        array $context = [],
    ): self {
        return new self($errorCode, $message, $context);
    }

    /**
     * 判断是否为客户端错误（4xx）
     */
    public function isClientError(): bool
    {
        $status = $this->errorCode->httpStatus();

        return $status >= 400 && $status < 500;
    }

    /**
     * 判断是否为服务器错误（5xx）
     */
    public function isServerError(): bool
    {
        $status = $this->errorCode->httpStatus();

        return $status >= 500;
    }
}
