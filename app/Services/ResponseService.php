<?php

namespace App\Services;

use BackedEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

/**
 * 响应服务类
 * 提供统一的API响应格式和工具方法
 */
class ResponseService
{
    public function sendResponse(mixed $data, string $message, int $code = ResponseAlias::HTTP_OK): JsonResponse
    {
        return Response::json($this->makeResponse($message, $data), $code);
    }

    public function sendError(
        string $message = 'Fail',
        int $code = ResponseAlias::HTTP_BAD_REQUEST,
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

        return Response::json($response, $code);
    }

    public function sendEnumError(BackedEnum $enum, ?int $httpCode = null): JsonResponse
    {
        $code = is_int($enum->value) ? $enum->value : 400;
        $message = method_exists($enum, 'message') ? $enum->message() : $enum->name;
        $status = method_exists($enum, 'httpStatus') ? $enum->httpStatus() : ($httpCode ?? 400);

        return $this->sendError($message, $status, null, $code);
    }

    public function sendSuccess(
        string $message = 'success',
        mixed $data = null,
        int $code = ResponseAlias::HTTP_OK,
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

        return Response::json($response, $code);
    }

    public function sendRetrieved(string $modelName, mixed $data): JsonResponse
    {
        return $this->sendSuccess("{$modelName} retrieved successfully", $data);
    }

    public function sendCreated(string $modelName, mixed $data): JsonResponse
    {
        return $this->sendSuccess("{$modelName} created successfully", $data, ResponseAlias::HTTP_CREATED);
    }

    public function sendUpdated(string $modelName, mixed $data): JsonResponse
    {
        return $this->sendSuccess("{$modelName} updated successfully", $data);
    }

    public function sendDeleted(string $modelName, mixed $data = null): JsonResponse
    {
        return $this->sendSuccess("{$modelName} deleted successfully", $data);
    }

    public function paginatorData(
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

        if (! empty($extraData)) {
            $data['extras'] = $extraData;
        }

        if ($resource && class_exists($resource)) {
            $data['items'] = $resource::collection($paginator->items());
        } else {
            $data['items'] = $paginator->items();
        }

        return $data;
    }

    public function sendPaginatorData(
        LengthAwarePaginator $paginator,
        ?string $resource = null,
        array $extraData = [],
    ): JsonResponse {
        return $this->sendRetrieved('Resource', $this->paginatorData($paginator, $resource, $extraData));
    }

    public function makeResponse(string $message, mixed $data): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    public function makeError(string $message, array $data = []): array
    {
        $res = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($data)) {
            $res['data'] = $data;
        }

        return $res;
    }
}
