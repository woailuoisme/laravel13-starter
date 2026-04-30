<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use SanderMuller\FluentValidation\FluentFormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SigninVerifyRequest extends FluentFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_token' => FluentRule::string()->required()->min(20),
            'code' => FluentRule::numeric()->required()->digits(6),
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'challenge_token' => [
                'description' => 'The challenge token returned by the signin request.',
                'example' => '4f0a9f6b1d2c3e4f5a6b7c8d9e0f123456789012',
            ],
            'code' => [
                'description' => 'The 6-digit verification code sent to the user email address.',
                'example' => '123456',
            ],
        ];
    }
}
