<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Enums\Gender;
use App\Http\Controllers\AppBaseController;
use App\Http\Resources\UserProfileResource;
use App\Http\Resources\V1\NotificationResource;
use App\Mail\V1\PasswordResetMail;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Services\WechatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Tymon\JWTAuth\JWTGuard;

/**
 * @group 用户认证
 */
class AuthController extends AppBaseController
{
    public function __construct(
        protected WechatService $wechatService,
        protected MediaService $mediaService,
    ) {
        parent::__construct();
    }

    /**
     * 用户登录 (昵称/邮箱/手机号 + 密码)
     *
     * @unauthenticated
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'nickname' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::where('nickname', $credentials['nickname'])
            ->orWhere('telephone', $credentials['nickname'])
            ->orWhere('email', $credentials['nickname'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password ?? '')) {
            return $this->sendError(__('auth.failed'), 401);
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return $this->sendResponse($this->generateTokenData($user), __('auth.login_success'));
    }

    /**
     * 用户注册
     *
     * @unauthenticated
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($data, $request) {
            return User::create([
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'nickname' => explode('@', $data['email'])[0],
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);
        });

        return $this->sendResponse($this->generateTokenData($user), __('auth.register_success'));
    }

    /**
     * 获取当前认证用户信息
     *
     * @authenticated
     */
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();
        $user->loadCount(['availableCoupons as user_coupon_count']);

        return $this->sendSuccess(data: new UserProfileResource($user));
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

        return $this->sendSuccess(__('auth.logout_success'));
    }

    /**
     * 更新用户个人资料
     *
     * @authenticated
     */
    public function profileUpdate(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $data = $request->validate([
            'avatar' => ['nullable', 'image', 'max:2048', 'mimes:jpeg,jpg,png,gif,webp'],
            'telephone' => [
                'nullable',
                'string',
                'min:10',
                'regex:/^1[3-9]\d{9}$/',
                Rule::unique('users', 'telephone')->ignore($user->id),
            ],
            'nickname' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(Gender::values())],
        ]);

        DB::transaction(function () use ($user, $data): void {
            $user->update($data);

            if (isset($data['avatar'])) {
                $this->mediaService->uploadSingle($user, $data['avatar'], 'avatar');
            }
        });

        return $this->sendSuccess(data: new UserProfileResource($user->fresh('media')));
    }

    /**
     * 请求重置密码邮件
     *
     * @unauthenticated
     */
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['old_password'], $user->password ?? '')) {
            return $this->sendError(__('auth.password_reset_wrong_credentials'), 401);
        }

        $newPasswordHash = base64_encode(Hash::make($data['new_password']));
        $resetUrl = URL::temporarySignedRoute(
            'v1.auth.password.confirm',
            now()->addMinutes(60),
            ['user' => $user->id, 'hash' => $newPasswordHash],
        );

        Mail::to($user->email)->queue(new PasswordResetMail($resetUrl));

        return $this->sendSuccess(__('auth.password_reset_sent'));
    }

    /**
     * 确认并执行密码重置
     *
     * @unauthenticated
     */
    public function confirmPasswordReset(Request $request): JsonResponse
    {
        $request->validate([
            'user' => ['required', 'integer', 'exists:users,id'],
            'hash' => ['required', 'string'],
        ]);

        if (!URL::hasValidSignature()) {
            return $this->sendError(__('auth.password_reset_invalid_link'), 403);
        }

        $user = User::findOrFail($request->user);
        $user->update(['password' => base64_decode($request->hash)]);

        return $this->sendSuccess(__('auth.password_reset_success'));
    }

    /**
     * 重定向至第三方登录 (OAuth)
     *
     * @unauthenticated
     */
    public function redirectToProvider(string $provider): JsonResponse
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        return response()->json([
            'url' => $driver->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    /**
     * 处理第三方登录回调
     *
     * @unauthenticated
     */
    public function handleProviderCallback(string $provider): JsonResponse
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);
        $socialUser = $driver->stateless()->user();
        $idColumn = $provider . '_id';

        $user = User::where($idColumn, $socialUser->getId())
            ->orWhere('email', $socialUser->getEmail())
            ->first();

        if ($user) {
            $user->update([
                $idColumn => $socialUser->getId(),
                'last_login_at' => now(),
            ]);
        } else {
            $user = User::create([
                $idColumn => $socialUser->getId(),
                'nickname' => $socialUser->getNickname() ?: $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'password' => Hash::make(str()->random(24)),
                'avatar' => $socialUser->getAvatar(),
                'last_login_at' => now(),
            ]);
        }

        return $this->sendResponse($this->generateTokenData($user), __('auth.login_success'));
    }

    /**
     * 刷新访问令牌 (Token)
     *
     * @authenticated
     */
    public function refresh(): JsonResponse
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');

        return $this->sendSuccess(data: ['token' => $guard->refresh()]);
    }

    /**
     * 获取用户通知列表
     *
     * @authenticated
     */
    public function notifications(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $notifications = $user->notifications()
            ->paginate($request->integer('per_page', 15));

        return $this->sendResponse(
            NotificationResource::collection($notifications)->response()->getData(true),
            __('admin.notifications_fetched'),
        );
    }

    /**
     * 标记通知为已读
     *
     * @authenticated
     */
    public function markNotificationAsRead(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->sendSuccess(__('admin.notification_marked_as_read'));
    }

    /**
     * 标记所有通知为已读
     *
     * @authenticated
     */
    public function markAllNotificationsAsRead(): JsonResponse
    {
        $user = auth('api')->user();
        $user->unreadNotifications->markAsRead();

        return $this->sendSuccess(__('admin.all_notifications_marked_as_read'));
    }

    /**
     * 删除通知
     *
     * @authenticated
     */
    public function deleteNotification(string $id): JsonResponse
    {
        $user = auth('api')->user();
        $user->notifications()->findOrFail($id)->delete();

        return $this->sendSuccess(__('admin.notification_deleted'));
    }

    /**
     * Generate Token Data
     */
    protected function generateTokenData(User $user): array
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');
        $token = $guard->login($user);

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $guard->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'nickname' => $user->nickname,
                'email' => $user->email,
                'avatar' => $user->avatar_url,
            ],
        ];
    }
}
