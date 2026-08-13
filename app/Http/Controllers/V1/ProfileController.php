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
use Spatie\MediaLibrary\HasMedia;
use Throwable;
use Tymon\JWTAuth\JWTGuard;

/**
 * @group 用户资料
 */
final class ProfileController extends AppBaseController
{
    public function __construct(
        protected MediaService $mediaService,
    ) {}

    /**
     * 获取当前认证用户信息
     *
     * @authenticated
     *
     * 返回当前用户资料，包含常用展示字段。
     *
     * @responseField data.id 用户 ID
     * @responseField data.nickname 用户昵称
     * @responseField data.email 用户邮箱
     * @responseField data.avatar 用户头像地址
     *
     * @responseFile storage/responses/v1/auth/user-profile.json
     */
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();
        $user->loadCount(['availableCoupons as user_coupon_count']);

        return $this->sendSuccess(data: new UserProfileResource($user));
    }

    /**
     * 更新用户个人资料
     *
     * @authenticated
     *
     * @throws Throwable
     *
     * 返回更新后的用户资料，字段与 `me()` 一致。
     *
     * @responseField data.id 用户 ID
     * @responseField data.nickname 用户昵称
     * @responseField data.email 用户邮箱
     * @responseField data.avatar 用户头像地址
     *
     * @responseFile storage/responses/v1/auth/user-profile.json
     */
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

    /**
     * 退出登录
     *
     * @authenticated
     */
    public function logout(): JsonResponse
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');
        $guard->logout();

        $msg = __('auth.logout_success');

        return $this->sendSuccess(is_string($msg) ? $msg : 'Logout successful');
    }

    /**
     * 刷新访问令牌 (Token)
     *
     * @authenticated
     *
     * 返回刷新后的访问令牌。
     *
     * @responseField data.token 刷新后的访问令牌
     *
     * @responseFile storage/responses/v1/auth/refresh-token.json
     */
    public function refresh(): JsonResponse
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');

        return $this->sendSuccess(data: ['token' => $guard->refresh()]);
    }
}
