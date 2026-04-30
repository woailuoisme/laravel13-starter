<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use Illuminate\Support\Str;
use SanderMuller\FluentValidation\FluentFormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ForgotPasswordRequest extends FluentFormRequest
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
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'The email address that should receive the password reset code.',
                'example' => 'user@example.com',
            ],
        ];
    }
}
