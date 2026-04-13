<?php

declare(strict_types=1);

namespace App\Mail\V1;

class PasswordResetMail extends AuthVerificationCodeMail
{
    public function __construct(string $code)
    {
        parent::__construct(code: $code, action: 'reset_password');
    }
}
