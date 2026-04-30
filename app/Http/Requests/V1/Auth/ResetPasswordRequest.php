<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use Illuminate\Support\Str;
use SanderMuller\FluentValidation\FluentFormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ResetPasswordRequest extends FluentFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower((string) $this->input('email')),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => FluentRule::email(defaults: false)->required()->max(255),
            'code' => FluentRule::numeric()->required()->digits(6),
            'password' => FluentRule::string()->required()->min(6)->confirmed(),
            'password_confirmation' => FluentRule::string()->required()->min(6),
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'The email address associated with the account.',
                'example' => 'user@example.com',
            ],
            'code' => [
                'description' => 'The 6-digit password reset code sent to the email address.',
                'example' => '123456',
            ],
            'password' => [
                'description' => 'The new password for the account.',
                'example' => 'new-password123',
            ],
            'password_confirmation' => [
                'description' => 'Confirmation for the new password.',
                'example' => 'new-password123',
            ],
        ];
    }
}
