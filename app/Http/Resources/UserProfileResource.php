<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'gender' => $this->gender,
            'avatar' => $this->getFirstMediaUrl('avatar') ?: $this->avatar,
            'last_login_at' => $this->last_login_at?->toDateTimeString(),
            'coupon_count' => $this->whenCounted('availableCoupons'),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
