<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AuthResultResource extends JsonResource
{
    public function __construct(
        User $resource,
        private readonly string $accessToken,
        private readonly int $expiresIn,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->accessToken,
            'token_type' => 'bearer',
            'expires_in' => $this->expiresIn,
            'user' => [
                'id' => $this->resource->id,
                'nickname' => $this->resource->nickname,
                'email' => $this->resource->email,
                'avatar' => $this->resource->avatar_url,
            ],
        ];
    }
}
