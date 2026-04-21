<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SigninRequest extends FormRequest
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
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'The account email address.',
                'example' => 'user@example.com',
            ],
            'password' => [
                'description' => 'The account password.',
                'example' => 'password123',
            ],
        ];
    }
}
