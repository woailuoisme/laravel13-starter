<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Throwable;

class ApiException extends Exception
{
    public readonly array $data;

    public readonly int $customCode;

    public readonly int $httpCode;

    public static array $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing', // RFC2518
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        10000 => '无效信息',
    ];

    public function __construct(
        string $message = 'API Error',
        int $customCode = ResponseAlias::HTTP_BAD_REQUEST,
        int $httpCode = ResponseAlias::HTTP_BAD_REQUEST,
        array $data = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpCode, $previous);

        $this->customCode = $customCode;
        $this->httpCode = $httpCode;
        $this->data = $data;

        // 记录异常到日志
        $this->logException();
    }

    /**
     * 记录异常到日志
     */
    private function logException(): void
    {
        $logContext = [
            'custom_code' => $this->customCode,
            'http_code' => $this->httpCode,
            'data' => $this->data,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];

        // 根据HTTP状态码确定日志级别
        $logLevel = $this->getLogLevel();

        Log::log(
            $logLevel,
            "API Exception: {$this->getMessage()}",
            $logContext,
        );
    }

    /**
     * 根据HTTP状态码确定日志级别
     */
    private function getLogLevel(): string
    {
        return match (true) {
            $this->httpCode >= 500 => 'error', // 服务器错误
            $this->httpCode >= 400 => 'warning', // 客户端错误
            default => 'info', // 其他情况
        };
    }

    public function getCustomCode(): int
    {
        return $this->customCode ?? ResponseAlias::HTTP_BAD_REQUEST;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getErrors(): array
    {
        return $this->data ?? [];
    }
}
