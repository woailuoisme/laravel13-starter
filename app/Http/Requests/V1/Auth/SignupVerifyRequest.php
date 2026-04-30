<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use Illuminate\Support\Str;
use SanderMuller\FluentValidation\FluentFormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SignupVerifyRequest extends FluentFormRequest
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
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'The email address used during signup.',
                'example' => 'new-user@example.com',
            ],
            'code' => [
                'description' => 'The 6-digit verification code sent to the email address.',
                'example' => '123456',
            ],
        ];
    }
}
