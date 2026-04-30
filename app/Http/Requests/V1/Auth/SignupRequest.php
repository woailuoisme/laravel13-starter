<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use Illuminate\Support\Str;
use SanderMuller\FluentValidation\FluentFormRequest;
use SanderMuller\FluentValidation\FluentRule;

class SignupRequest extends FluentFormRequest
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
            'email' => FluentRule::email(defaults: false)->required()->max(255)->unique('users', 'email'),
            'password' => FluentRule::string()->required()->min(6)->confirmed(),
            'password_confirmation' => FluentRule::string()->required()->min(6),
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'The email address used to create the account.',
                'example' => 'new-user@example.com',
            ],
            'password' => [
                'description' => 'The account password.',
                'example' => 'new-password123',
            ],
            'password_confirmation' => [
                'description' => 'Confirmation for the account password.',
                'example' => 'new-password123',
            ],
        ];
    }
}
