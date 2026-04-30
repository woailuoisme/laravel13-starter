<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use SanderMuller\FluentValidation\FluentFormRequest;
use SanderMuller\FluentValidation\FluentRule;

class LoginRequest extends FluentFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nickname' => FluentRule::string()->required(),
            'password' => FluentRule::string()->required()->min(6),
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'nickname' => [
                'description' => 'The account nickname, email address, or telephone number.',
                'example' => 'user@example.com',
            ],
            'password' => [
                'description' => 'The account password.',
                'example' => 'password123',
            ],
        ];
    }
}
