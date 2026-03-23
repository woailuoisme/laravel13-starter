<?php

return [
    'labels' => [
        'search' => '搜索',
        'base_url' => '基础 URL',
    ],

    'auth' => [
        'none' => '此 API 不需要认证。',
        'instruction' => [
            'query' => <<<'TEXT'
                要在请求中进行身份验证，请在请求中包含查询参数 **`:parameterName`**。
                TEXT,
            'body' => <<<'TEXT'
                要在请求中进行身份验证，请在请求正文中包含参数 **`:parameterName`**。
                TEXT,
            'query_or_body' => <<<'TEXT'
                要在请求中进行身份验证，请在查询字符串或请求正文中包含参数 **`:parameterName`**。
                TEXT,
            'bearer' => <<<'TEXT'
                要在请求中进行身份验证，请包含一个 **`Authorization`** 标头，其值为 **`"Bearer :placeholder"`**。
                TEXT,
            'basic' => <<<'TEXT'
                要在请求中进行身份验证，请按 **`"Basic {credentials}"`** 格式包含一个 **`Authorization`** 标头。
                `{credentials}` 的值应该是您的用户名/ID 和密码，中间用冒号 (:) 连接，然后进行 base64 编码。
                TEXT,
            'header' => <<<'TEXT'
                要在请求中进行身份验证，请包含一个 **`:parameterName`** 标头，其值为 **`":placeholder"`**。
                TEXT,
        ],
        'details' => <<<'TEXT'
            所有经过身份验证的端点在下面的文档中都标有 `requires authentication` 徽章。
            TEXT,
    ],

    'headings' => [
        'introduction' => '介绍',
        'auth' => '身份验证请求',
    ],

    'endpoint' => [
        'request' => '请求',
        'headers' => '标头',
        'url_parameters' => 'URL 参数',
        'body_parameters' => '正文参数',
        'query_parameters' => '查询参数',
        'response' => '响应',
        'response_fields' => '响应字段',
        'example_request' => '示例请求',
        'example_response' => '示例响应',
        'responses' => [
            'binary' => '二进制数据',
            'empty' => '空响应',
        ],
    ],

    'try_it_out' => [
        'open' => '立即试用 ⚡',
        'cancel' => '取消 🛑',
        'send' => '发送请求 💥',
        'loading' => '⏱ 正在发送...',
        'received_response' => '已收到响应',
        'request_failed' => '请求失败，错误为',
        'error_help' => <<<'TEXT'
            提示：请检查您的网络连接是否正常。
            如果您是此 API 的维护者，请验证您的 API 是否正在运行并且已启用 CORS。
            您可以查看开发人员工具控制台以获取调试信息。
            TEXT,
    ],

    'links' => [
        'postman' => '查看 Postman 集合',
        'openapi' => '查看 OpenAPI 规范',
    ],
];
