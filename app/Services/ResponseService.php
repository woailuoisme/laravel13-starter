<?php

namespace App\Services;

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
    private static ?self $instance = null;

    /**
     * 获取单例实例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 发送成功响应
     *
     * @param mixed $data 响应数据
     * @param string $message 响应消息
     * @param int $code HTTP状态码
     */
    public function sendResponse(mixed $data, string $message, int $code = ResponseAlias::HTTP_OK): JsonResponse
    {
        return Response::json(self::makeResponse($message, $data), $code);
    }

    /**
     * 静态方法：发送成功响应
     *
     * @param mixed $data 响应数据
     * @param string $message 响应消息
     * @param int $code HTTP状态码
     */
    public static function response(mixed $data, string $message, int $code = ResponseAlias::HTTP_OK): JsonResponse
    {
        return self::getInstance()->sendResponse($data, $message, $code);
    }

    /**
     * 发送错误响应
     *
     * @param string $message 错误消息
     * @param int $code HTTP状态码
     * @param mixed|null $data 额外错误数据
     * @param int|null $customCode 自定义错误码
     */
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

    /**
     * 静态方法：发送错误响应
     *
     * @param string $message 错误消息
     * @param int $code HTTP状态码
     * @param mixed|null $data 额外错误数据
     * @param int|null $customCode 自定义错误码
     */
    public static function error(
        string $message = 'Fail',
        int $code = ResponseAlias::HTTP_BAD_REQUEST,
        mixed $data = null,
        ?int $customCode = null,
    ): JsonResponse {
        return self::getInstance()->sendError($message, $code, $data, $customCode);
    }

    /**
     * 发送基于枚举的错误响应
     *
     * @param \BackedEnum $enum 错误码枚举
     * @param int|null $httpCode HTTP状态码，如果枚举定义了httpStatus()则优先使用
     */
    public function sendEnumError(\BackedEnum $enum, ?int $httpCode = null): JsonResponse
    {
        $code = is_int($enum->value) ? $enum->value : 400;
        /** @var mixed $mixedEnum */
        $mixedEnum = $enum;
        $message = method_exists($enum, 'message') ? $mixedEnum->message() : $enum->name;
        $status = method_exists($enum, 'httpStatus') ? $mixedEnum->httpStatus() : ($httpCode ?? 400);

        return $this->sendError($message, $status, null, $code);
    }

    /**
     * 静态方法：发送基于枚举的错误响应
     *
     * @param \BackedEnum $enum 错误码枚举
     * @param int|null $httpCode HTTP状态码
     */
    public static function enumError(\BackedEnum $enum, ?int $httpCode = null): JsonResponse
    {
        return self::getInstance()->sendEnumError($enum, $httpCode);
    }

    /**
     * 发送成功响应
     *
     * @param string $message 成功消息
     * @param mixed|null $data 响应数据
     * @param int $code HTTP状态码
     * @param int|null $customCode 自定义状态码
     */
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

    /**
     * 静态方法：发送成功响应
     *
     * @param string $message 成功消息
     * @param mixed|null $data 响应数据
     * @param int $code HTTP状态码
     * @param int|null $customCode 自定义状态码
     */
    public static function success(
        string $message = 'success',
        mixed $data = null,
        int $code = ResponseAlias::HTTP_OK,
        ?int $customCode = null,
    ): JsonResponse {
        return self::getInstance()->sendSuccess($message, $data, $code, $customCode);
    }

    /**
     * 资源获取成功响应
     *
     * @param string $modelName 模型名称
     * @param mixed $data 数据
     */
    public function sendRetrieved(string $modelName, mixed $data): JsonResponse
    {
        return $this->sendSuccess("{$modelName} retrieved successfully", $data);
    }

    /**
     * 静态方法：资源获取成功响应
     *
     * @param string $modelName 模型名称
     * @param mixed $data 数据
     */
    public static function retrieved(string $modelName, mixed $data): JsonResponse
    {
        return self::getInstance()->sendRetrieved($modelName, $data);
    }

    /**
     * 资源创建成功响应
     *
     * @param string $modelName 模型名称
     * @param mixed $data 数据
     */
    public function sendCreated(string $modelName, mixed $data): JsonResponse
    {
        return $this->sendSuccess("{$modelName} created successfully", $data, ResponseAlias::HTTP_CREATED);
    }

    /**
     * 静态方法：资源创建成功响应
     *
     * @param string $modelName 模型名称
     * @param mixed $data 数据
     */
    public static function created(string $modelName, mixed $data): JsonResponse
    {
        return self::getInstance()->sendCreated($modelName, $data);
    }

    /**
     * 资源更新成功响应
     *
     * @param string $modelName 模型名称
     * @param mixed $data 数据
     */
    public function sendUpdated(string $modelName, mixed $data): JsonResponse
    {
        return $this->sendSuccess("{$modelName} updated successfully", $data);
    }

    /**
     * 静态方法：资源更新成功响应
     *
     * @param string $modelName 模型名称
     * @param mixed $data 数据
     */
    public static function updated(string $modelName, mixed $data): JsonResponse
    {
        return self::getInstance()->sendUpdated($modelName, $data);
    }

    /**
     * 资源删除成功响应
     *
     * @param string $modelName 模型名称
     * @param mixed|null $data 额外数据
     */
    public function sendDeleted(string $modelName, mixed $data = null): JsonResponse
    {
        return $this->sendSuccess("{$modelName} deleted successfully", $data);
    }

    /**
     * 静态方法：资源删除成功响应
     *
     * @param string $modelName 模型名称
     * @param mixed|null $data 额外数据
     */
    public static function deleted(string $modelName, mixed $data = null): JsonResponse
    {
        return self::getInstance()->sendDeleted($modelName, $data);
    }

    /**
     * 获取分页数据
     *
     * @param LengthAwarePaginator $paginator 分页器实例
     * @param string|null $resource 资源类名
     * @param array $extraData 额外数据
     */
    public function paginatorData(
        LengthAwarePaginator $paginator,
        ?string $resource = null,
        array $extraData = [],
    ): array {
        $data = [
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
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

    /**
     * 发送分页响应
     *
     * @param LengthAwarePaginator $paginator 分页器实例
     * @param string|null $resource 资源类名
     * @param array $extraData 额外数据
     */
    public function sendPaginatorData(
        LengthAwarePaginator $paginator,
        ?string $resource = null,
        array $extraData = [],
    ): JsonResponse {
        return $this->sendRetrieved('Resource', $this->paginatorData($paginator, $resource, $extraData));
    }

    /**
     * 静态方法：发送分页响应
     *
     * @param LengthAwarePaginator $paginator 分页器实例
     * @param string|null $resource 资源类名
     * @param array $extraData 额外数据
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        ?string $resource = null,
        array $extraData = [],
    ): JsonResponse {
        return self::getInstance()->sendPaginatorData($paginator, $resource, $extraData);
    }

    /**
     * 创建成功响应数组
     *
     * @param string $message 响应消息
     * @param mixed $data 响应数据
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
     * 创建错误响应数组
     *
     * @param string $message 错误消息
     * @param array $data 额外错误数据
     */
    public static function makeError(string $message, array $data = []): array
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
