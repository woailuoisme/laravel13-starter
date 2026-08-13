<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Throwable;

class ApiException extends Exception
{
    public static array $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing', // RFC2518
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        10_000 => '无效信息',
    ];

    public function __construct(
        string $message = 'API Error',
        public readonly int $customCode = ResponseAlias::HTTP_BAD_REQUEST,
        public readonly int $httpCode = ResponseAlias::HTTP_BAD_REQUEST,
        public readonly array $data = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpCode, $previous);

        $this->logException();
    }

    private function logException(): void
    {
        $logContext = [
            'custom_code' => $this->customCode,
            'http_code' => $this->httpCode,
            'data' => $this->data,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];

        Log::log(
            $this->getLogLevel(),
            "API Exception: {$this->getMessage()}",
            $logContext,
        );
    }

    private function getLogLevel(): string
    {
        return match (true) {
            $this->httpCode >= 500 => 'error',
            $this->httpCode >= 400 => 'warning',
            default => 'info',
        };
    }

    public function getCustomCode(): int
    {
        return $this->customCode;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getErrors(): array
    {
        return $this->data;
    }
}
