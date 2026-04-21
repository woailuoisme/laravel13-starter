<?php

declare(strict_types=1);

return [
    'notifications' => [
        'enabled' => 'Enabled successfully',
        'disabled' => 'Disabled successfully',
    ],
    'resources' => [
        'users' => [
            'model_label' => 'User',
            'plural_label' => 'Users',
            'fields' => [
                'name' => 'Name',
                'username' => 'Username',
                'email' => 'Email address',
                'phone' => 'Phone number',
                'avatar' => 'Avatar',
                'birthday' => 'Birthday',
                'gender' => 'Gender',
                'bio' => 'Bio',
                'email_verified_at' => 'Email verified at',
                'password' => 'Password',
                'open_id' => 'OpenID',
                'github_id' => 'GitHub ID',
                'google_id' => 'Google ID',
                'nickname' => 'Nickname',
                'telephone' => 'Telephone',
                'last_login_at' => 'Last login at',
                'last_login_ip' => 'Last login IP',
                'created_at' => 'Created at',
                'updated_at' => 'Updated at',
                'deleted_at' => 'Deleted at',
                'stripe_id' => 'Stripe ID',
                'pm_type' => 'Payment method type',
                'pm_last_four' => 'Payment method last four',
                'trial_ends_at' => 'Trial ends at',
            ],
        ],
        'admin_users' => [
            'model_label' => 'Admin User',
            'plural_label' => 'Admin Users',
            'fields' => [
                'username' => 'Username',
                'name' => 'Name',
                'email' => 'Email address',
                'password' => 'Password',
                'phone' => 'Phone number',
                'is_active' => 'Is active',
                'last_login_at' => 'Last login at',
                'last_login_ip' => 'Last login IP',
                'avatar_url' => 'Avatar URL',
                'created_at' => 'Created at',
                'updated_at' => 'Updated at',
                'deleted_at' => 'Deleted at',
            ],
        ],
    ],
    'pages' => [
        'manage_ecommerce' => [
            'fields' => [
                'site_name' => 'Site name',
                'is_shop_open' => 'Shop open',
                'free_shipping_threshold' => 'Free shipping threshold',
                'allowed_payment_gateways' => 'Allowed payment gateways',
                'close_reason' => 'Close reason',
            ],
        ],
    ],
];
