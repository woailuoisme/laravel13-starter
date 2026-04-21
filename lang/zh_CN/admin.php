<?php

declare(strict_types=1);

return [
    'notifications' => [
        'enabled' => '开启成功',
        'disabled' => '关闭成功',
    ],
    'resources' => [
        'users' => [
            'model_label' => '用户',
            'plural_label' => '用户',
            'fields' => [
                'name' => '姓名',
                'username' => '用户名',
                'email' => '邮箱地址',
                'phone' => '手机号',
                'avatar' => '头像',
                'birthday' => '生日',
                'gender' => '性别',
                'bio' => '简介',
                'email_verified_at' => '邮箱验证时间',
                'password' => '密码',
                'open_id' => 'OpenID',
                'github_id' => 'GitHub ID',
                'google_id' => 'Google ID',
                'nickname' => '昵称',
                'telephone' => '电话号码',
                'last_login_at' => '最后登录时间',
                'last_login_ip' => '最后登录 IP',
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
                'deleted_at' => '删除时间',
                'stripe_id' => 'Stripe ID',
                'pm_type' => '支付方式类型',
                'pm_last_four' => '支付方式后四位',
                'trial_ends_at' => '试用结束时间',
            ],
        ],
        'admin_users' => [
            'model_label' => '后台用户',
            'plural_label' => '后台用户',
            'fields' => [
                'username' => '用户名',
                'name' => '姓名',
                'email' => '邮箱地址',
                'password' => '密码',
                'phone' => '手机号',
                'is_active' => '是否启用',
                'last_login_at' => '最后登录时间',
                'last_login_ip' => '最后登录 IP',
                'avatar_url' => '头像地址',
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
                'deleted_at' => '删除时间',
            ],
        ],
    ],
    'pages' => [
        'manage_ecommerce' => [
            'fields' => [
                'site_name' => '站点名称',
                'is_shop_open' => '是否开店',
                'free_shipping_threshold' => '包邮门槛',
                'allowed_payment_gateways' => '可用支付方式',
                'close_reason' => '关闭原因',
            ],
        ],
    ],
];
