<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthChallengeResource extends JsonResource
{
    /**
     * @param array<string, mixed> $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource['status'],
            'action' => $this->resource['action'],
            'email' => $this->resource['email'] ?? null,
            'challenge_token' => $this->resource['challenge_token'] ?? null,
            'resend_in' => $this->resource['resend_in'] ?? 0,
        ];
    }
}
