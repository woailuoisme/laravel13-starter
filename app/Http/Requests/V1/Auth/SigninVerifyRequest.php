<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SigninVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'min:20'],
            'code' => ['required', 'digits:6'],
        ];
    }
}
