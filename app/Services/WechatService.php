<?php

declare(strict_types=1);

namespace App\Services;

class WechatService
{
    public function getMiniProgramSession(string $code): array
    {
        // Mocked WeChat API logic
        return [
            'openid' => 'mock_openid_'.$code,
            'session_key' => 'mock_session_key',
        ];
    }

    public function getPhoneNumber(string $code): array
    {
        // Mocked WeChat API logic
        return [
            'phoneNumber' => '13800138000',
        ];
    }
}
