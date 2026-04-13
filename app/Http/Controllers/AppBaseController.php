<?php

namespace App\Http\Controllers;

use App\Services\ResponseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * 应用基础控制器
 * 提供统一的API响应格式和常用工具方法
 */
class AppBaseController extends Controller
{
    /**
     * 响应服务实例
     */
    protected ResponseService $responseService;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->responseService = ResponseService::getInstance();
    }

    /**
     * 发送成功响应
     *
     * @param mixed $data 响应数据
     * @param string $message 响应消息
     * @param int $code HTTP状态码
     */
    public function sendResponse(mixed $data, string $message, int $code = Response::HTTP_OK): JsonResponse
    {
        return $this->responseService->sendResponse($data, $message, $code);
    }

    /**
     * 获取指定连接的数据模型实例
     *
     * @template T of Model
     *
     * @param class-string<T> $modelClass 模型类名
     * @param string $connection 数据库连接名称
     * @return Model
     */
    public function getModel(string $modelClass, string $connection = 'mysql'): Model
    {
        /** @var Model $model */
        $model = new $modelClass();
        $model->setConnection($connection);

        return $model;
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
        string $message = 'failure',
        int $code = 400,
        mixed $data = null,
        ?int $customCode = null,
    ): JsonResponse {
        return $this->responseService->sendError($message, $code, $data, $customCode);
    }

    /**
     * 发送基于枚举的错误响应
     *
     * @param \BackedEnum $enum 错误码枚举
     * @param int|null $httpCode HTTP状态码，如果枚举定义了httpStatus()则优先使用
     */
    public function sendEnumError(\BackedEnum $enum, ?int $httpCode = null): JsonResponse
    {
        return $this->responseService->sendEnumError($enum, $httpCode);
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
        int $code = 200,
        ?int $customCode = null,
    ): JsonResponse {
        return $this->responseService->sendSuccess($message, $data, $code, $customCode);
    }

    /**
     * 资源获取成功响应
     *
     * @param string $modelName 模型名称
     * @param mixed $data 数据
     */
    public function sendRetrieved(string $modelName, mixed $data): JsonResponse
    {
        return $this->responseService->sendRetrieved($modelName, $data);
    }

    /**
     * 资源创建成功响应
     *
     * @param string $modelName 模型名称
     * @param mixed $data 数据
     */
    public function sendCreated(string $modelName, mixed $data): JsonResponse
    {
        return $this->responseService->sendCreated($modelName, $data);
    }

    /**
     * 资源更新成功响应
     *
     * @param string $modelName 模型名称
     * @param mixed $data 数据
     */
    public function sendUpdated(string $modelName, mixed $data): JsonResponse
    {
        return $this->responseService->sendUpdated($modelName, $data);
    }

    /**
     * 资源删除成功响应
     *
     * @param string $modelName 模型名称
     * @param mixed|null $data 额外数据
     */
    public function sendDeleted(string $modelName, mixed $data = null): JsonResponse
    {
        return $this->responseService->sendDeleted($modelName, $data);
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
        return $this->responseService->paginatorData($paginator, $resource, $extraData);
    }

    /**
     * 发送分页响应
     *
     * @param LengthAwarePaginator $paginator 分页器实例
     * @param string|null $resource 资源类名
     * @param array $extraData 额外数据
     */
    public function sendPaginator(
        LengthAwarePaginator $paginator,
        ?string $resource = null,
        array $extraData = [],
    ): JsonResponse {
        return $this->responseService->sendPaginatorData($paginator, $resource, $extraData);
    }
}
