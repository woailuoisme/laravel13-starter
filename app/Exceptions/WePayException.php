<?php

namespace App\Exceptions;

use Exception;

/**
 * 支付网关异常类
 *
 * 用于处理支付网关相关的异常情况
 */
class WePayException extends Exception
{
    /**
     * 构造函数
     *
     * @param string $message 异常消息
     * @param int $code 异常代码
     * @param Exception|null $previous 上一个异常
     */
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 创建支付失败异常
     *
     * @param string $message 错误消息
     */
    public static function paymentFailed(string $message): static
    {
        return new static("支付失败: {$message}", 1001);
    }

    /**
     * 创建配置错误异常
     *
     * @param string $message 错误消息
     */
    public static function configError(string $message): static
    {
        return new static("配置错误: {$message}", 1002);
    }

    /**
     * 创建网络错误异常
     *
     * @param string $message 错误消息
     */
    public static function networkError(string $message): static
    {
        return new static("网络错误: {$message}", 1003);
    }

    /**
     * 创建签名验证失败异常
     *
     * @param string $message 错误消息
     */
    public static function signatureError(string $message = '签名验证失败'): static
    {
        return new static($message, 1004);
    }

    /**
     * 根据微信支付错误码创建友好的异常信息
     *
     * @param string $wechatErrorCode 微信错误码
     * @param string $originalMessage 原始错误消息
     */
    public static function wechatPayError(string $wechatErrorCode, string $originalMessage = ''): static
    {
        $friendlyMessages = [
            'APPID_MCHID_NOT_MATCH' => '商户配置错误，请联系客服处理',
            'INVALID_REQUEST' => '请求参数有误，请重试',
            'PARAM_ERROR' => '参数错误，请检查支付信息',
            'ORDERNOTEXIST' => '订单不存在或已过期',
            'OUT_TRADE_NO_USED' => '订单号重复，请重新下单',
            'NOAUTH' => '商户无权限，请联系客服',
            'AMOUNT_LIMIT' => '金额超出限制',
            'NOTENOUGH' => '余额不足',
            'ORDERPAID' => '订单已支付',
            'ORDERCLOSED' => '订单已关闭',
            'SYSTEMERROR' => '系统繁忙，请稍后重试',
            'APPID_NOT_EXIST' => '应用配置错误，请联系客服',
            'MCHID_NOT_EXIST' => '商户号不存在，请联系客服',
            'SIGN_ERROR' => '签名错误，请重试',
            'LACK_PARAMS' => '缺少必要参数，请重试',
            'NOT_UTF8' => '编码格式错误，请重试',
            'FREQUENCY_LIMITED' => '请求过于频繁，请稍后重试',
            'BANKERROR' => '银行系统异常，请稍后重试',
            'USERPAYING' => '用户支付中，请稍后查询',
            'USER_ACCOUNT_ABNORMAL' => '用户账户异常，请联系客服',
            'INVALID_TRANSACTIONID' => '无效的交易号',
            'XML_FORMAT_ERROR' => '数据格式错误，请重试',
            'REQUIRE_POST_METHOD' => '请求方式错误',
            'POST_DATA_EMPTY' => '请求数据为空',
            'NOT_FOUND' => '请求的资源不存在',
        ];

        $message = $friendlyMessages[$wechatErrorCode] ?? '支付服务暂时不可用，请稍后重试';

        // 在开发环境下显示详细错误信息
        if (config('app.debug') && $originalMessage) {
            $message .= " (错误码: {$wechatErrorCode}, 详情: {$originalMessage})";
        }

        return new static($message, 1005);
    }

    /**
     * 解析微信支付错误响应并创建友好异常
     *
     * @param string $responseBody 响应体
     * @param int $statusCode HTTP状态码
     */
    public static function fromWechatResponse(string $responseBody, int $statusCode = 400): static
    {
        try {
            $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

            $errorCode = $data['code'] ?? 'UNKNOWN_ERROR';
            $errorMessage = $data['message'] ?? '未知错误';

            return self::wechatPayError($errorCode, $errorMessage);
        } catch (\JsonException $e) {
            // 如果不是JSON格式，返回通用错误
            return new static('支付服务异常，请稍后重试', 1006);
        }
    }
}
