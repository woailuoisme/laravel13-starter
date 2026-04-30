<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\V1\Auth\LoginRequest;
use App\Http\Requests\V1\Auth\ProfileUpdateRequest;
use App\Http\Requests\V1\Auth\ResendCodeRequest;
use App\Http\Requests\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\V1\Auth\SigninRequest;
use App\Http\Requests\V1\Auth\SigninVerifyRequest;
use App\Http\Requests\V1\Auth\SignupRequest;
use App\Http\Requests\V1\Auth\SignupVerifyRequest;
use App\Http\Resources\UserProfileResource;
use App\Http\Resources\V1\Auth\AuthChallengeResource;
use App\Http\Resources\V1\Auth\AuthResultResource;
use App\Http\Resources\V1\NotificationResource;
use App\Models\User;
use App\Services\Auth\AuthFlowService;
use App\Services\Media\MediaService;
use App\Services\WechatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Throwable;
use Tymon\JWTAuth\JWTGuard;

/**
 * @group 用户认证
 */
class AuthController extends AppBaseController
{
    public function __construct(
        protected WechatService $wechatService,
        protected MediaService $mediaService,
        protected AuthFlowService $authFlowService,
    ) {
        parent::__construct();
    }

    /**
     * 兼容登录入口 (昵称/邮箱/手机号 + 密码)
     *
     * @unauthenticated
     * @bodyParam nickname string required 用户昵称、手机号或邮箱。Example: user@example.com
     * @bodyParam password string required 登录密码，最少 6 位。Example: password123
     *
     * 登录成功时返回 token 和用户信息；需要验证码时返回挑战信息。
     *
     * @responseField data.access_token 访问令牌
     * @responseField data.user.id 用户 ID
     * @responseField data.user.nickname 用户昵称
     * @responseField data.status 登录挑战状态
     * @responseField data.challenge_token 登录挑战令牌
     *
     * @responseFile storage/responses/v1/auth/auth-result.json
     * @responseFile storage/responses/v1/auth/auth-challenge-login.json
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $nickname = $request->string('nickname')->toString();

        $user = User::query()
            ->where('nickname', $nickname)
            ->orWhere('telephone', $nickname)
            ->orWhere('email', $nickname)
            ->first();

        if (!$user) {
            return $this->sendError(__('auth.invalid_credentials'), 401);
        }

        $result = $this->authFlowService->requestSignin(
            email: $user->email,
            password: $request->string('password')->toString(),
            ip: $request->ip(),
            forceChallenge: $this->forceChallenge($request),
        );

        if ($result['status'] === 'authenticated') {
            /** @var User $authenticatedUser */
            $authenticatedUser = $result['user'];

            return $this->sendAuthResult($authenticatedUser, __('auth.login_success'));
        }

        return $this->sendResponse(
            new AuthChallengeResource($result),
            __('auth.challenge_required'),
        );
    }

    /**
     * 发起登录
     *
     * @unauthenticated
     * @bodyParam email string required 登录邮箱。Example: signin@example.com
     * @bodyParam password string required 登录密码。Example: password123
     *
     * 登录成功时返回 token 和用户信息；需要验证码时返回挑战信息。
     *
     * @responseField data.access_token 访问令牌
     * @responseField data.user.id 用户 ID
     * @responseField data.user.nickname 用户昵称
     * @responseField data.status 登录挑战状态
     * @responseField data.challenge_token 登录挑战令牌
     *
     * @responseFile storage/responses/v1/auth/auth-result.json
     * @responseFile storage/responses/v1/auth/auth-challenge-login.json
     */
    public function signinRequest(SigninRequest $request): JsonResponse
    {
        $result = $this->authFlowService->requestSignin(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            ip: $request->ip(),
            forceChallenge: $this->forceChallenge($request),
        );

        if ($result['status'] === 'authenticated') {
            /** @var User $user */
            $user = $result['user'];

            return $this->sendAuthResult($user, __('auth.login_success'));
        }

        return $this->sendResponse(
            new AuthChallengeResource($result),
            __('auth.challenge_required'),
        );
    }

    /**
     * 提交登录验证码挑战
     *
     * @unauthenticated
     * @bodyParam challenge_token string required 登录挑战令牌。Example: challenge-token
     * @bodyParam code string required 6 位邮箱验证码。Example: 123456
     *
     * 验证成功后返回 token 和用户信息。
     *
     * @responseField data.access_token 访问令牌
     * @responseField data.user.id 用户 ID
     * @responseField data.user.nickname 用户昵称
     *
     * @responseFile storage/responses/v1/auth/auth-result.json
     */
    public function signinVerify(SigninVerifyRequest $request): JsonResponse
    {
        $user = $this->authFlowService->verifySignin(
            challengeToken: $request->string('challenge_token')->toString(),
            code: $request->string('code')->toString(),
            ip: $request->ip(),
        );

        return $this->sendAuthResult($user, __('auth.login_success'));
    }

    /**
     * 兼容注册入口
     *
     * @unauthenticated
     * @bodyParam email string required 注册邮箱。Example: signup@example.com
     * @bodyParam password string required 登录密码，最少 6 位。Example: password123
     * @bodyParam password_confirmation string required 确认密码，必须与 password 一致。Example: password123
     *
     * 返回注册验证码挑战信息。
     *
     * @responseField data.status 验证码发送状态
     * @responseField data.email 目标邮箱
     * @responseField data.challenge_token 验证挑战令牌
     *
     * @responseFile storage/responses/v1/auth/auth-challenge-register.json
     */
    public function register(SignupRequest $request): JsonResponse
    {
        return $this->signupRequest($request);
    }

    /**
     * 发起注册并发送邮箱验证码
     *
     * @unauthenticated
     * @bodyParam email string required 注册邮箱。Example: signup@example.com
     * @bodyParam password string required 登录密码，最少 6 位。Example: password123
     * @bodyParam password_confirmation string required 确认密码，必须与 password 一致。Example: password123
     *
     * 返回注册验证码挑战信息。
     *
     * @responseField data.status 验证码发送状态
     * @responseField data.email 目标邮箱
     * @responseField data.challenge_token 验证挑战令牌
     *
     * @responseFile storage/responses/v1/auth/auth-challenge-register.json
     */
    public function signupRequest(SignupRequest $request): JsonResponse
    {
        $result = $this->authFlowService->requestSignup(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            ip: $request->ip(),
        );

        return $this->sendResponse(
            new AuthChallengeResource($result),
            __('auth.verification_code_sent'),
        );
    }

    /**
     * 验证注册邮箱验证码并创建账号
     *
     * @unauthenticated
     * @bodyParam email string required 注册邮箱。Example: signup@example.com
     * @bodyParam code string required 6 位邮箱验证码。Example: 123456
     *
     * 验证成功后返回 token 和用户信息。
     *
     * @responseField data.access_token 访问令牌
     * @responseField data.user.id 用户 ID
     * @responseField data.user.email 用户邮箱
     *
     * @responseFile storage/responses/v1/auth/auth-result.json
     */
    public function signupVerify(SignupVerifyRequest $request): JsonResponse
    {
        $user = $this->authFlowService->verifySignup(
            email: $request->string('email')->toString(),
            code: $request->string('code')->toString(),
            ip: $request->ip(),
        );

        return $this->sendAuthResult($user, __('auth.register_success'));
    }

    /**
     * 重发验证码
     *
     * @unauthenticated
     * @bodyParam email string required 需要重发验证码的邮箱。Example: user@example.com
     * @bodyParam action string required 验证码业务类型，可选 register、login、reset_password。Example: login
     * @bodyParam challenge_token string 登录挑战令牌，action 为 login 时传入。Example: challenge-token
     *
     * 返回新的验证码挑战信息。
     *
     * @responseField data.status 验证码发送状态
     * @responseField data.challenge_token 验证挑战令牌
     *
     * @responseFile storage/responses/v1/auth/auth-challenge-login.json
     */
    public function resendCode(ResendCodeRequest $request): JsonResponse
    {
        $result = $this->authFlowService->resendCode(
            email: $request->string('email')->toString(),
            action: $request->string('action')->toString(),
            challengeToken: $request->filled('challenge_token')
                ? $request->string('challenge_token')->toString()
                : null,
        );

        return $this->sendResponse(
            new AuthChallengeResource($result),
            __('auth.verification_code_resent'),
        );
    }

    /**
     * 发起忘记密码
     *
     * @unauthenticated
     * @bodyParam email string required 需要找回密码的邮箱。Example: user@example.com
     *
     * 返回找回密码验证码挑战信息。
     *
     * @responseField data.status 验证码发送状态
     * @responseField data.challenge_token 验证挑战令牌
     *
     * @responseFile storage/responses/v1/auth/auth-challenge-reset-password.json
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->authFlowService->requestPasswordReset(
            email: $request->string('email')->toString(),
        );

        return $this->sendResponse(
            new AuthChallengeResource($result),
            __('auth.password_reset_sent'),
        );
    }

    /**
     * 使用验证码重置密码
     *
     * @unauthenticated
     * @bodyParam email string required 需要重置密码的邮箱。Example: user@example.com
     * @bodyParam code string required 6 位邮箱验证码。Example: 123456
     * @bodyParam password string required 新密码，最少 6 位。Example: new-password123
     * @bodyParam password_confirmation string required 确认密码，必须与 password 一致。Example: new-password123
     *
     * 重置结果只包含 `data.status`。
     *
     * @responseField data.status 重置结果状态
     *
     * @responseFile storage/responses/v1/auth/reset-password.json
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authFlowService->resetPassword(
            email: $request->string('email')->toString(),
            code: $request->string('code')->toString(),
            password: $request->string('password')->toString(),
        );

        return $this->sendSuccess(
            __('auth.password_reset_success'),
            ['status' => 'completed'],
        );
    }

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
        $user = auth('api')->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data): void {
            $user->update($data);

            if (isset($data['avatar'])) {
                $this->mediaService->uploadSingle($user, $data['avatar'], 'avatar');
            }
        });

        return $this->sendSuccess(data: new UserProfileResource($user->fresh('media')));
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

    /**
     * 重定向至第三方登录 (OAuth)
     *
     * @unauthenticated
     *
     * 返回第三方授权地址。
     *
     * @responseField url 第三方授权地址
     *
     * @responseFile storage/responses/v1/auth/provider-redirect.json
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
     *
     * 返回 token 和用户信息。
     *
     * @responseField data.access_token 访问令牌
     * @responseField data.user.id 用户 ID
     * @responseField data.user.nickname 用户昵称
     * @responseField data.user.email 用户邮箱
     *
     * @responseFile storage/responses/v1/auth/auth-result.json
     */
    public function handleProviderCallback(string $provider): JsonResponse
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);
        $socialUser = $driver->stateless()->user();
        $idColumn = $provider . '_id';

        $user = User::query()
            ->where($idColumn, $socialUser->getId())
            ->orWhere('email', $socialUser->getEmail())
            ->first();

        if ($user) {
            $user->update([
                $idColumn => $socialUser->getId(),
                'last_login_at' => now(),
            ]);
        } else {
            $displayName = $socialUser->getNickname() ?: $socialUser->getName() ?: 'social_user';

            $user = User::create([
                'name' => $displayName,
                'nickname' => $displayName,
                $idColumn => $socialUser->getId(),
                'email' => $socialUser->getEmail(),
                'password' => str()->random(24),
                'avatar' => $socialUser->getAvatar(),
                'last_login_at' => now(),
            ]);
        }

        return $this->sendAuthResult($user, __('auth.login_success'));
    }

    /**
     * 获取用户通知列表
     *
     * @authenticated
     *
     * 返回分页后的通知列表。
     *
     * @responseField data.data.id 通知 ID
     * @responseField data.data.title 通知标题
     * @responseField data.data.body 通知内容
     * @responseField data.data.read_at 已读时间
     *
     * @responseFile storage/responses/v1/auth/notifications.json
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

    protected function sendAuthResult(User $user, string $message): JsonResponse
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');
        $token = $guard->login($user);

        return $this->sendResponse(
            new AuthResultResource($user, $token, $guard->factory()->getTTL() * 60),
            $message,
        );
    }

    private function forceChallenge(Request $request): bool
    {
        return mb_strtolower((string) $request->header('X-Auth-Risk')) === 'challenge';
    }
}
