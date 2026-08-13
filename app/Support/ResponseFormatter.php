<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResponseFormatter
{
    /**
     * 返回统一结构的 JSON API 响应
     */
    public static function response(
        mixed $data = null,
        ?string $message = null,
        int $code = 200,
        bool $status = true,
    ): JsonResponse {
        if ($code < 100 || $code > 599) {
            Log::warning('Invalid HTTP status code provided', ['code' => $code]);
            $code = 500;
        }

        return response()->json(
            ['success' => $status, 'code' => $code, 'message' => $message, 'data' => $data],
            $code,
        );
    }

    /**
     * 发送成功响应
     */
    public static function sendResponse(mixed $data, string $message, int $code = Response::HTTP_OK): JsonResponse
    {
        return response()->json(self::makeResponse($message, $data), $code);
    }

    /**
     * 发送错误响应
     */
    public static function sendError(
        string $message = 'Fail',
        int $code = Response::HTTP_BAD_REQUEST,
        mixed $data = null,
        ?int $customCode = null,
        mixed $errors = null,
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $customCode ?? $code,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }
        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * 发送基于枚举的错误响应
     */
    public static function sendEnumError(BackedEnum $enum, ?int $httpCode = null): JsonResponse
    {
        $code = is_int($enum->value) ? $enum->value : 400;
        $message = method_exists($enum, 'message') ? (string) $enum->message() : $enum->name;
        $status = method_exists($enum, 'httpStatus') ? (int) $enum->httpStatus() : $httpCode ?? 400;

        return self::sendError($message, $status, null, $code);
    }

    /**
     * 发送成功响应
     */
    public static function sendSuccess(
        string $message = 'success',
        mixed $data = null,
        int $code = Response::HTTP_OK,
        ?int $customCode = null,
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'code' => $customCode ?? $code,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * 发送资源获取成功响应
     */
    public static function sendRetrieved(string $modelName, mixed $data): JsonResponse
    {
        return self::sendSuccess("{$modelName} retrieved successfully", $data);
    }

    /**
     * 发送资源创建成功响应
     */
    public static function sendCreated(string $modelName, mixed $data): JsonResponse
    {
        return self::sendSuccess("{$modelName} created successfully", $data, Response::HTTP_CREATED);
    }

    /**
     * 发送资源更新成功响应
     */
    public static function sendUpdated(string $modelName, mixed $data): JsonResponse
    {
        return self::sendSuccess("{$modelName} updated successfully", $data);
    }

    /**
     * 发送资源删除成功响应
     */
    public static function sendDeleted(string $modelName, mixed $data = null): JsonResponse
    {
        return self::sendSuccess("{$modelName} deleted successfully", $data);
    }

    /**
     * 构造分页响应数据
     */
    public static function paginatorData(
        LengthAwarePaginator $paginator,
        ?string $resource = null,
        array $extraData = [],
    ): array {
        $data = [
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];

        if ($extraData !== []) {
            $data['extras'] = $extraData;
        }

        $data['items'] = $resource && class_exists($resource)
            ? $resource::collection($paginator->items())
            : $paginator->items();

        return $data;
    }

    /**
     * 发送分页响应
     */
    public static function sendPaginatorData(
        LengthAwarePaginator $paginator,
        ?string $resource = null,
        array $extraData = [],
    ): JsonResponse {
        return self::sendRetrieved('Resource', self::paginatorData($paginator, $resource, $extraData));
    }

    /**
     * 构造成功响应数组
     */
    public static function makeResponse(string $message, mixed $data): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * 构造错误响应数组
     */
    public static function makeError(string $message, array $data = []): array
    {
        $res = [
            'success' => false,
            'message' => $message,
        ];

        if ($data !== []) {
            $res['data'] = $data;
        }

        return $res;
    }
}
