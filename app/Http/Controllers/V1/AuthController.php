<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Enums\Gender;
use App\Http\Controllers\AppBaseController;
use App\Http\Requests\V1\Auth\ForgotPasswordRequest;
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
use Illuminate\Validation\Rule;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
     * @response 200 {
     *   "success": true,
     *   "message": "登录成功",
     *   "code": 200,
     *   "data": {
     *     "access_token": "jwt-token",
     *     "token_type": "bearer",
     *     "expires_in": 1209600,
     *     "user": {
     *       "id": 1,
     *       "nickname": "user",
     *       "email": "user@example.com",
     *       "avatar": null
     *     }
     *   }
     * }
     * @response 200 {
     *   "success": true,
     *   "message": "需要完成安全验证",
     *   "code": 200,
     *   "data": {
     *     "status": "challenge_required",
     *     "action": "login",
     *     "email": "user@example.com",
     *     "challenge_token": "challenge-token",
     *     "resend_in": 60
     *   }
     * }
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'nickname' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::query()
            ->where('nickname', $credentials['nickname'])
            ->orWhere('telephone', $credentials['nickname'])
            ->orWhere('email', $credentials['nickname'])
            ->first();

        if (!$user) {
            return $this->sendError(__('auth.invalid_credentials'), 401);
        }

        $result = $this->authFlowService->requestSignin(
            email: $user->email,
            password: $credentials['password'],
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
     * 兼容注册入口
     *
     * @unauthenticated
     * @bodyParam email string required 注册邮箱。Example: signup@example.com
     * @bodyParam password string required 登录密码，最少 6 位。Example: password123
     * @bodyParam password_confirmation string required 确认密码，必须与 password 一致。Example: password123
     * @response 200 {
     *   "success": true,
     *   "message": "验证码已发送",
     *   "code": 200,
     *   "data": {
     *     "status": "code_sent",
     *     "action": "register",
     *     "email": "signup@example.com",
     *     "challenge_token": null,
     *     "resend_in": 60
     *   }
     * }
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
     * @response 200 {
     *   "success": true,
     *   "message": "验证码已发送",
     *   "code": 200,
     *   "data": {
     *     "status": "code_sent",
     *     "action": "register",
     *     "email": "signup@example.com",
     *     "challenge_token": null,
     *     "resend_in": 60
     *   }
     * }
     */
    public function signupRequest(SignupRequest $request): JsonResponse
    {
        try {
            $result = $this->authFlowService->requestSignup(
                email: $request->string('email')->toString(),
                password: $request->string('password')->toString(),
                ip: $request->ip(),
            );
        } catch (HttpException $exception) {
            return $this->sendError($exception->getMessage(), $exception->getStatusCode());
        }

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
     * @response 200 {
     *   "success": true,
     *   "message": "注册成功",
     *   "code": 200,
     *   "data": {
     *     "access_token": "jwt-token",
     *     "token_type": "bearer",
     *     "expires_in": 1209600,
     *     "user": {
     *       "id": 1,
     *       "nickname": "signup",
     *       "email": "signup@example.com",
     *       "avatar": null
     *     }
     *   }
     * }
     */
    public function signupVerify(SignupVerifyRequest $request): JsonResponse
    {
        try {
            $user = $this->authFlowService->verifySignup(
                email: $request->string('email')->toString(),
                code: $request->string('code')->toString(),
                ip: $request->ip(),
            );
        } catch (HttpException $exception) {
            return $this->sendError($exception->getMessage(), $exception->getStatusCode());
        }

        return $this->sendAuthResult($user, __('auth.register_success'));
    }

    /**
     * 发起登录
     *
     * @unauthenticated
     * @bodyParam email string required 登录邮箱。Example: signin@example.com
     * @bodyParam password string required 登录密码。Example: password123
     * @response 200 {
     *   "success": true,
     *   "message": "登录成功",
     *   "code": 200,
     *   "data": {
     *     "access_token": "jwt-token",
     *     "token_type": "bearer",
     *     "expires_in": 1209600,
     *     "user": {
     *       "id": 1,
     *       "nickname": "signin",
     *       "email": "signin@example.com",
     *       "avatar": null
     *     }
     *   }
     * }
     * @response 200 {
     *   "success": true,
     *   "message": "需要完成安全验证",
     *   "code": 200,
     *   "data": {
     *     "status": "challenge_required",
     *     "action": "login",
     *     "email": "signin@example.com",
     *     "challenge_token": "challenge-token",
     *     "resend_in": 60
     *   }
     * }
     */
    public function signinRequest(SigninRequest $request): JsonResponse
    {
        try {
            $result = $this->authFlowService->requestSignin(
                email: $request->string('email')->toString(),
                password: $request->string('password')->toString(),
                ip: $request->ip(),
                forceChallenge: $this->forceChallenge($request),
            );
        } catch (HttpException $exception) {
            return $this->sendError($exception->getMessage(), $exception->getStatusCode());
        }

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
     * @response 200 {
     *   "success": true,
     *   "message": "登录成功",
     *   "code": 200,
     *   "data": {
     *     "access_token": "jwt-token",
     *     "token_type": "bearer",
     *     "expires_in": 1209600,
     *     "user": {
     *       "id": 1,
     *       "nickname": "signin",
     *       "email": "signin@example.com",
     *       "avatar": null
     *     }
     *   }
     * }
     */
    public function signinVerify(SigninVerifyRequest $request): JsonResponse
    {
        try {
            $user = $this->authFlowService->verifySignin(
                challengeToken: $request->string('challenge_token')->toString(),
                code: $request->string('code')->toString(),
                ip: $request->ip(),
            );
        } catch (HttpException $exception) {
            return $this->sendError($exception->getMessage(), $exception->getStatusCode());
        }

        return $this->sendAuthResult($user, __('auth.login_success'));
    }

    /**
     * 重发验证码
     *
     * @unauthenticated
     * @bodyParam email string required 需要重发验证码的邮箱。Example: user@example.com
     * @bodyParam action string required 验证码业务类型，可选 register、login、reset_password。Example: login
     * @bodyParam challenge_token string 登录挑战令牌，action 为 login 时传入。Example: challenge-token
     * @response 200 {
     *   "success": true,
     *   "message": "验证码已重新发送",
     *   "code": 200,
     *   "data": {
     *     "status": "code_sent",
     *     "action": "login",
     *     "email": "user@example.com",
     *     "challenge_token": "challenge-token",
     *     "resend_in": 60
     *   }
     * }
     */
    public function resendCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'action' => ['required', Rule::in(['register', 'login', 'reset_password'])],
            'challenge_token' => ['nullable', 'string'],
        ]);

        try {
            $result = $this->authFlowService->resendCode(
                email: (string) $data['email'],
                action: (string) $data['action'],
                challengeToken: $data['challenge_token'] ?? null,
            );
        } catch (HttpException $exception) {
            return $this->sendError($exception->getMessage(), $exception->getStatusCode());
        }

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
     * @response 200 {
     *   "success": true,
     *   "message": "重置密码验证码已发送",
     *   "code": 200,
     *   "data": {
     *     "status": "code_sent",
     *     "action": "reset_password",
     *     "email": "user@example.com",
     *     "challenge_token": null,
     *     "resend_in": 60
     *   }
     * }
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $result = $this->authFlowService->requestPasswordReset(
                email: $request->string('email')->toString(),
            );
        } catch (HttpException $exception) {
            return $this->sendError($exception->getMessage(), $exception->getStatusCode());
        }

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
     * @response 200 {
     *   "success": true,
     *   "message": "密码重置成功",
     *   "code": 200,
     *   "data": {
     *     "status": "completed"
     *   }
     * }
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->authFlowService->resetPassword(
                email: $request->string('email')->toString(),
                code: $request->string('code')->toString(),
                password: $request->string('password')->toString(),
            );
        } catch (HttpException $exception) {
            return $this->sendError($exception->getMessage(), $exception->getStatusCode());
        }

        return $this->sendSuccess(
            __('auth.password_reset_success'),
            ['status' => 'completed'],
        );
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
     * @throws Throwable
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
