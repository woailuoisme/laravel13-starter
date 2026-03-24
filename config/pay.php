<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | WeChat Pay Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your WeChat Pay settings.
    |
    */

    'wechat' => [
        // 公众号/小程序/APP的 APPID
        'app_id' => env('WECHAT_PAY_APP_ID', ''),

        // 商户号
        'mch_id' => env('WECHAT_PAY_MCH_ID', ''),

        // API v3 密钥
        'key' => env('WECHAT_PAY_V3_KEY', ''),

        // 商户 API 私钥文件路径 (apiclient_key.pem)
        'private_key_path' => env('WECHAT_PAY_PRIVATE_KEY_PATH', 'storage/certs/wechat/apiclient_key.pem'),

        // 商户证书序列号 (可在商户后台查看)
        'certificate_serial' => env('WECHAT_PAY_CERT_SERIAL', ''),

        // 商户公钥证书文件路径 (apiclient_cert.pem)，可选
        'cert_path' => env('WECHAT_PAY_CERT_PATH', 'storage/certs/wechat/apiclient_cert.pem'),

        // 支付回调地址
        'notify_url' => env('WECHAT_PAY_NOTIFY_URL', ''),

        // 退款回调地址
        'refund_notify_url' => env('WECHAT_PAY_REFUND_NOTIFY_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alipay Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Alipay settings.
    |
    */

    'alipay' => [
        'app_id' => env('ALIPAY_APP_ID', ''),
        'public_key' => env('ALIPAY_PUBLIC_KEY', ''),
        'private_key' => env('ALIPAY_PRIVATE_KEY', ''),
        'notify_url' => env('ALIPAY_NOTIFY_URL', ''),
        'return_url' => env('ALIPAY_RETURN_URL', ''),
        'sandbox' => env('ALIPAY_SANDBOX', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Alipay Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for cross-border Alipay payments.
    |
    */

    'alipay_global' => [
        'app_id' => env('ALIPAY_GLOBAL_APP_ID', ''),
        'private_key' => env('ALIPAY_GLOBAL_PRIVATE_KEY', ''),
        'alipay_public_key' => env('ALIPAY_GLOBAL_PUBLIC_KEY', ''),
        'notify_url' => env('ALIPAY_GLOBAL_NOTIFY_URL', ''),
        'return_url' => env('ALIPAY_GLOBAL_RETURN_URL', ''),
        'currency' => env('ALIPAY_GLOBAL_CURRENCY', 'USD'),
        'sandbox' => env('ALIPAY_GLOBAL_SANDBOX', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration (Managed via Cashier)
    |--------------------------------------------------------------------------
    |
    | Basic options for Stripe integration. Full config at config/cashier.php.
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY', ''),
        'secret' => env('STRIPE_SECRET', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
    ],
];
