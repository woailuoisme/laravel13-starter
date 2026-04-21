<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ResetPasswordRequest extends FormRequest
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

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:6'],
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
