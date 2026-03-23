<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * 支付宝异常类
 *
 * 用于处理支付宝支付过程中的各种异常情况
 */
class AlipayException extends Exception
{
    /**
     * 支付失败异常
     *
     * @param string $message 错误信息
     * @param array $context 上下文信息
     */
    public static function paymentFailed(string $message, array $context = []): static
    {
        Log::error('支付宝支付失败', array_merge(['message' => $message], $context));

        return new static("支付失败: {$message}", 4001);
    }

    /**
     * 配置错误异常
     *
     * @param string $message 错误信息
     */
    public static function configError(string $message): static
    {
        Log::error('支付宝配置错误', ['message' => $message]);

        return new static("配置错误: {$message}", 4002);
    }

    /**
     * 网络错误异常
     *
     * @param string $message 错误信息
     */
    public static function networkError(string $message): static
    {
        Log::error('支付宝网络错误', ['message' => $message]);

        return new static("网络错误: {$message}", 4003);
    }

    /**
     * 签名验证失败异常
     *
     * @param string $message 错误信息
     */
    public static function signatureError(string $message): static
    {
        Log::error('支付宝签名验证失败', ['message' => $message]);

        return new static("签名验证失败: {$message}", 4004);
    }

    /**
     * 订单状态异常
     *
     * @param string $message 错误信息
     */
    public static function orderStatusError(string $message): static
    {
        Log::error('支付宝订单状态异常', ['message' => $message]);

        return new static("订单状态异常: {$message}", 4005);
    }

    /**
     * 参数验证异常
     *
     * @param string $message 错误信息
     */
    public static function validationError(string $message): static
    {
        Log::error('支付宝参数验证失败', ['message' => $message]);

        return new static("参数验证失败: {$message}", 4006);
    }

    /**
     * 根据支付宝响应创建友好的异常
     *
     * @param object $response 支付宝响应对象
     * @param string $originalMessage 原始错误信息
     */
    public static function fromAlipayResponse($response, string $originalMessage = ''): static
    {
        $code = $response->code ?? '';
        $msg = $response->msg ?? '';
        $subCode = $response->subCode ?? '';
        $subMsg = $response->subMsg ?? '';

        return self::alipayError($code, $subCode, $msg, $subMsg, $originalMessage);
    }

    /**
     * 创建支付宝友好错误异常
     *
     * @param string $code 主错误码
     * @param string $subCode 子错误码
     * @param string $msg 主错误信息
     * @param string $subMsg 子错误信息
     * @param string $originalMessage 原始错误信息
     */
    public static function alipayError(string $code, string $subCode = '', string $msg = '', string $subMsg = '', string $originalMessage = ''): static
    {
        // 根据错误码获取友好的错误信息
        $friendlyMessage = self::getFriendlyMessage($code, $subCode);

        // 如果没有找到友好信息，使用原始信息
        if ($friendlyMessage === null) {
            $friendlyMessage = $subMsg ?: $msg ?: $originalMessage ?: '支付服务暂时不可用，请稍后重试';
        }

        // 在调试模式下显示详细错误信息
        if (config('app.debug')) {
            $debugInfo = [];
            if ($code) {
                $debugInfo[] = "错误码: {$code}";
            }
            if ($subCode) {
                $debugInfo[] = "子错误码: {$subCode}";
            }
            if ($msg) {
                $debugInfo[] = "错误信息: {$msg}";
            }
            if ($subMsg) {
                $debugInfo[] = "详细信息: {$subMsg}";
            }

            if (! empty($debugInfo)) {
                $friendlyMessage .= ' ('.implode(', ', $debugInfo).')';
            }
        }

        Log::error('支付宝错误', [
            'code' => $code,
            'sub_code' => $subCode,
            'msg' => $msg,
            'sub_msg' => $subMsg,
            'friendly_message' => $friendlyMessage,
            'original_message' => $originalMessage,
        ]);

        return new static($friendlyMessage, 4007);
    }

    /**
     * 获取友好的错误信息
     *
     * @param string $code 主错误码
     * @param string $subCode 子错误码
     */
    protected static function getFriendlyMessage(string $code, string $subCode = ''): ?string
    {
        // 主错误码映射
        $mainErrorMessages = [
            '10000' => null, // 成功，不需要友好信息
            '20000' => '服务不可用，请稍后重试',
            '20001' => '授权权限不足，请联系客服处理',
            '40001' => '缺少必选参数，请检查请求参数',
            '40002' => '非法的参数，请检查参数格式',
            '40003' => '条件不满足，请检查业务参数',
            '40004' => '业务处理失败，请稍后重试',
            '40006' => '权限不足，请联系客服处理',
        ];

        // 子错误码映射（更具体的错误信息）
        $subErrorMessages = [
            // 系统错误
            'SYSTEM_ERROR' => '系统繁忙，请稍后重试',
            'SERVICE_CURRENTLY_UNAVAILABLE' => '服务暂时不可用，请稍后重试',
            'INVALID_PARAMETER' => '参数错误，请检查请求参数',
            'MISSING_PARAMETER' => '缺少必要参数，请检查请求参数',

            // 签名相关
            'INVALID_SIGN' => '签名验证失败，请检查签名参数',
            'INVALID_SIGN_TYPE' => '签名类型不正确',
            'INVALID_CHARSET' => '字符集不正确',
            'INVALID_DIGEST' => '摘要不正确',

            // 应用相关
            'INVALID_APP_ID' => '应用ID不正确，请检查配置',
            'APP_NOT_SET' => '应用未设置，请先配置应用',
            'APPID_NOT_EXIST' => '应用不存在，请检查应用ID',
            'APP_DOMAIN_NOT_MATCH' => '应用域名不匹配',

            // 商户相关
            'PARTNER_ERROR' => '商户信息有误，请联系客服处理',
            'SELLER_NOT_EXIST' => '商户不存在',
            'PARTNER_NOT_SIGN_PROTOCOL' => '商户未签约该产品',
            'MERCHANT_STATUS_ERROR' => '商户状态异常，请联系客服处理',

            // 订单相关
            'TRADE_NOT_EXIST' => '交易不存在',
            'TRADE_HAS_SUCCESS' => '交易已支付成功',
            'TRADE_STATUS_ERROR' => '交易状态不正确',
            'TRADE_HAS_CLOSE' => '交易已关闭',
            'TRADE_FINISHED' => '交易已完成',
            'OUT_TRADE_NO_USED' => '商户订单号重复',
            'TRADE_NOT_ALLOW_REFUND' => '该交易不允许退款',
            'REFUND_AMT_NOT_EQUAL_TOTAL' => '退款金额超出可退款范围',
            'REASON_TRADE_BEEN_FREEZEN' => '交易被冻结',

            // 金额相关
            'TOTAL_FEE_EXCEED' => '订单金额超出限制',
            'PAYMENT_AMOUNT_ERROR' => '支付金额错误',
            'REFUND_AMOUNT_ERROR' => '退款金额错误',
            'AMOUNT_NOT_ENOUGH' => '余额不足',

            // 用户相关
            'BUYER_NOT_EXIST' => '买家不存在',
            'BUYER_SELLER_EQUAL' => '买家和卖家不能相同',
            'USER_NOT_EXIST' => '用户不存在',
            'USER_NOT_LOGIN' => '用户未登录',
            'USER_STATUS_ERROR' => '用户状态异常',

            // 支付相关
            'PAYMENT_FAIL' => '支付失败，请重新支付',
            'PAYMENT_REQUEST_HAS_RISK' => '支付请求存在风险，已被拦截',
            'BUYER_PAYMENT_AMOUNT_DAY_LIMIT_ERROR' => '买家付款日限额超限',
            'BEYOND_PAY_RESTRICTION' => '商户收款额度超限',
            'BEYOND_PER_RECEIPT_RESTRICTION' => '商户收款笔数超限',
            'BUYER_BALANCE_NOT_ENOUGH' => '买家余额不足',
            'BUYER_BANKCARD_BALANCE_NOT_ENOUGH' => '买家银行卡余额不足',

            // 退款相关
            'REFUND_FEE_ERROR' => '退款金额错误',
            'HAS_NO_EFFECTIVE_REFUND' => '没有有效的退款',
            'REFUND_AMT_EXCEED_LIMIT' => '退款金额超出限制',
            'REFUND_DEPOSIT_NOT_ENOUGH' => '退款保证金不足',

            // 证书相关
            'CERT_MISSING' => '缺少证书文件',
            'INVALID_CERT' => '证书无效',
            'CERT_EXPIRED' => '证书已过期',

            // 接口相关
            'API_NOT_FOUND' => '接口不存在',
            'METHOD_NOT_SUPPORTED' => '不支持的请求方法',
            'TIMESTAMP_ERROR' => '时间戳参数错误',
            'BIZ_CONTENT_NOT_VALID' => '业务参数不正确',

            // 风控相关
            'RISK_LEVEL_LIMIT' => '风险等级限制，无法完成操作',
            'PAYMENT_INFO_INCONSISTENCY' => '支付信息不一致',
            'USER_FACE_PAYMENT_SWITCH_OFF' => '用户当面付功能已关闭',

            // 其他常见错误
            'ACQ.SYSTEM_ERROR' => '系统错误，请稍后重试',
            'ACQ.INVALID_PARAMETER' => '参数无效',
            'ACQ.ACCESS_FORBIDDEN' => '无权限使用接口',
            'ACQ.EXIST_FORBIDDEN_WORD' => '订单信息中包含违禁词',
            'ACQ.PARTNER_ERROR' => '应用ID或商户号配置错误',
            'ACQ.TOTAL_FEE_EXCEED' => '订单金额超出限制',
            'ACQ.PAYMENT_FAIL' => '支付失败',
            'ACQ.BUYER_BALANCE_NOT_ENOUGH' => '用户余额不足',
            'ACQ.BUYER_BANKCARD_BALANCE_NOT_ENOUGH' => '用户银行卡余额不足',
            'ACQ.ERROR_BALANCE_PAYMENT_DISABLE' => '余额支付功能关闭',
            'ACQ.BUYER_SELLER_EQUAL' => '买卖家不能相同',
            'ACQ.TRADE_HAS_SUCCESS' => '交易已被支付',
            'ACQ.TRADE_HAS_CLOSE' => '交易已经关闭',
            'ACQ.BUYER_PAYMENT_AMOUNT_DAY_LIMIT_ERROR' => '买家付款日限额超限',
            'ACQ.BEYOND_PAY_RESTRICTION' => '商户收款额度超限',
            'ACQ.BEYOND_PER_RECEIPT_RESTRICTION' => '商户收款笔数超限',
            'ACQ.NOT_SUPPORT_TRANS_PAYEE' => '不支持转账到该收款方',
            'ACQ.CLIENT_VERSION_NOT_SUPPORT' => '买家客户端版本不支持该功能',
        ];

        // 优先返回子错误码的友好信息
        if ($subCode && isset($subErrorMessages[$subCode])) {
            return $subErrorMessages[$subCode];
        }

        // 返回主错误码的友好信息
        if ($code && isset($mainErrorMessages[$code])) {
            return $mainErrorMessages[$code];
        }

        return null;
    }
}
