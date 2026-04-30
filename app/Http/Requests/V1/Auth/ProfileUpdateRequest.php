<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use App\Enums\Gender;
use SanderMuller\FluentValidation\FluentFormRequest;
use SanderMuller\FluentValidation\FluentRule;

class ProfileUpdateRequest extends FluentFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => FluentRule::image()->nullable()->max(2048)->mimes('jpeg', 'jpg', 'png', 'gif', 'webp'),
            'telephone' => FluentRule::string()
                ->nullable()
                ->min(10)
                ->regex('/^1[3-9]\d{9}$/')
                ->unique('users', 'telephone', fn ($rule) => $rule->ignore($this->user())),
            'nickname' => FluentRule::string()->nullable()->max(255),
            'gender' => FluentRule::string()->nullable()->in(Gender::values()),
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'avatar' => [
                'description' => 'The profile avatar image.',
                'example' => 'avatar.jpg',
            ],
            'telephone' => [
                'description' => 'The user telephone number.',
                'example' => '13800138000',
            ],
            'nickname' => [
                'description' => 'The user nickname.',
                'example' => 'new_nickname',
            ],
            'gender' => [
                'description' => 'The user gender value.',
                'example' => 'male',
            ],
        ];
    }
}
