<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AdminUserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * @property int $id
 * @property string $username 登录用户名
 * @property string $name 真实姓名
 * @property string $email 邮箱地址
 * @property string $password 加密密码
 * @property string|null $phone 手机号
 * @property bool $is_active 是否启用: 0 禁用, 1 启用
 * @property Carbon|null $last_login_at 最后登录时间
 * @property string|null $last_login_ip 最后登录IP
 * @property string|null $avatar_url 头像连接
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at 软删除时间
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @method static \Database\Factories\AdminUserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereAvatarUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereLastLoginIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser withoutTrashed()
 * @mixin \Eloquent
 */
#[Fillable(['username', 'name', 'email', 'phone', 'password', 'is_active', 'last_login_at', 'last_login_ip', 'avatar_url'])]
#[Hidden(['password', 'remember_token'])]
class AdminUser extends Authenticatable implements FilamentUser, JWTSubject
{
    /** @use HasFactory<AdminUserFactory> */
    use HasFactory;
    use HasRoles;
    use SoftDeletes;
    use Notifiable;

    protected string $guard_name = 'filament';

    /**
     * Determine if the user can access the Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
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
}
