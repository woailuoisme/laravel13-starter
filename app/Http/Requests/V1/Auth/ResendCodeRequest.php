<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use SanderMuller\FluentValidation\FluentFormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ResendCodeRequest extends FluentFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => FluentRule::email(defaults: false)->required()->max(255),
            'action' => FluentRule::string()->required()->in(['register', 'login', 'reset_password']),
            'challenge_token' => FluentRule::string()->nullable(),
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'The email address that should receive the verification code.',
                'example' => 'user@example.com',
            ],
            'action' => [
                'description' => 'The verification flow to resend a code for.',
                'example' => 'login',
            ],
            'challenge_token' => [
                'description' => 'The login challenge token when resending a login code.',
                'example' => 'challenge-token',
            ],
        ];
    }
}
