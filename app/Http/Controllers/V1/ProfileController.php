<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\V1\Auth\ProfileUpdateRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromFile;
use Spatie\MediaLibrary\HasMedia;
use Throwable;
use Tymon\JWTAuth\JWTGuard;

#[Group('用户资料')]
final class ProfileController extends AppBaseController
{
    public function __construct(
        protected MediaService $mediaService,
    ) {}

    #[Endpoint('获取当前认证用户信息', '返回当前登录用户的个人详细资料，包含常用展示字段与优惠券数量统计。')]
    #[Authenticated]
    #[ResponseField('data.id', 'int', '用户 ID')]
    #[ResponseField('data.nickname', 'string', '用户昵称')]
    #[ResponseField('data.email', 'string', '用户邮箱')]
    #[ResponseField('data.avatar', 'string', '用户头像地址')]
    #[ResponseFromFile('storage/responses/v1/auth/user-profile.json')]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();
        $user->loadCount(['availableCoupons as user_coupon_count']);

        return $this->sendSuccess(data: new UserProfileResource($user));
    }

    /**
     * @throws Throwable
     */
    #[Endpoint('更新用户个人资料', '更新当前用户的个人资料（昵称、头像等）。')]
    #[Authenticated]
    #[ResponseField('data.id', 'int', '用户 ID')]
    #[ResponseField('data.nickname', 'string', '用户昵称')]
    #[ResponseField('data.email', 'string', '用户邮箱')]
    #[ResponseField('data.avatar', 'string', '用户头像地址')]
    #[ResponseFromFile('storage/responses/v1/auth/user-profile.json')]
    public function profileUpdate(ProfileUpdateRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        DB::transaction(function () use ($user, $data, $request): void {
            $user->fill($data)->save();

            $avatar = $request->file('avatar');
            if ($avatar instanceof UploadedFile) {
                assert($user instanceof HasMedia, 'Authenticated user must implement HasMedia');
                $this->mediaService->uploadSingle($user, $avatar, 'avatar');
            }
        });

        $freshUser = $user->fresh();

        return $this->sendSuccess(data: new UserProfileResource($freshUser ?? $user));
    }

    #[Endpoint('退出登录', '销毁当前认证用户的 JWT 访问令牌。')]
    #[Authenticated]
    #[Response(['success' => true, 'message' => 'Logout successful'], 200)]
    public function logout(): JsonResponse
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');
        $guard->logout();

        $msg = __('auth.logout_success');

        return $this->sendSuccess(is_string($msg) ? $msg : 'Logout successful');
    }

    #[Endpoint('刷新访问令牌 (Token)', '使用当前有效的 JWT 令牌换取新的访问令牌。')]
    #[Authenticated]
    #[ResponseField('data.token', 'string', '刷新后的访问令牌')]
    #[ResponseFromFile('storage/responses/v1/auth/refresh-token.json')]
    public function refresh(): JsonResponse
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');

        return $this->sendSuccess(data: ['token' => $guard->refresh()]);
    }
}
