<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tymon\JWTAuth\Contracts\JWTSubject;

#[Fillable(['nickname', 'username', 'email', 'telephone', 'avatar', 'birthday', 'gender', 'bio', 'password', 'last_login_at', 'last_login_ip', 'open_id', 'github_id', 'google_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject, HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;
    use SoftDeletes;
    use InteractsWithMedia;
    use Billable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthday' => 'date',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function otpRecords(): HasMany
    {
        return $this->hasMany(OtpRecord::class);
    }

    public function availableCoupons(): HasMany
    {
        // Placeholder relationship for your coupons logic
        return $this->hasMany(self::class, 'id', 'id'); // stub
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('avatar') ?: ($this->avatar ?: '');
    }
}
