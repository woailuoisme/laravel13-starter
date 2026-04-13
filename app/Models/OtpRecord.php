<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $identifier
 * @property string $type
 * @property string|null $action 动作类型: register, login, reset_password, bind_mobile
 * @property string $code
 * @property Carbon|null $used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @method static \Database\Factories\OtpRecordFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpRecord whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpRecord whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpRecord whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpRecord whereIdentifier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpRecord whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpRecord whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpRecord whereUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OtpRecord whereUserId($value)
 * @mixin \Eloquent
 */
#[Fillable(['user_id', 'identifier', 'type', 'action', 'code', 'used_at', 'expires_at'])]
class OtpRecord extends Model
{
    /** @use HasFactory<\Database\Factories\OtpRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Relationship to the user (if authenticated)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
