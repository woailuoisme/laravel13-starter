<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Http\Controllers\AppBaseController;
use App\Http\Requests\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\V1\Auth\LoginRequest;
use App\Http\Requests\V1\Auth\ResendCodeRequest;
use App\Http\Requests\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\V1\Auth\SigninRequest;
use App\Http\Requests\V1\Auth\SigninVerifyRequest;
use App\Http\Requests\V1\Auth\SignupRequest;
use App\Http\Requests\V1\Auth\SignupVerifyRequest;
use App\Http\Resources\V1\Auth\AuthChallengeResource;
use App\Http\Resources\V1\Auth\AuthResultResource;
use App\Models\User;
use App\Services\Auth\AuthFlowService;
use App\Services\WechatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Tymon\JWTAuth\JWTGuard;

/**
 * @group 用户认证
 */
final class AuthController extends AppBaseController
{
    public function __construct(
        protected WechatService $wechatService,
        protected AuthFlowService $authFlowService,
    ) {}

    /**
     * 兼容登录入口 (昵称/邮箱/手机号 + 密码)
     *
     * @unauthenticated
     *
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

        if (! $user) {
            return $this->sendError(__('auth.invalid_credentials'), 401);
        }

        $result = $this->authFlowService->requestSignin(
            email: $user->email,
            password: $request->string('password')->toString(),
            ip: $request->ip(),
            challengeMode: $this->forceChallenge($request) ? 'force' : 'auto',
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
     *
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
            challengeMode: $this->forceChallenge($request) ? 'force' : 'auto',
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
     *
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
     *
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
     *
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
     *
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
     *
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
     *
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
     *
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
        $idColumn = $provider.'_id';

        /** @var User|null $user */
        $user = User::query()
            ->where($idColumn, $socialUser->getId())
            ->orWhere('email', $socialUser->getEmail())
            ->first();

        if ($user) {
            /** @var User $user */
            $user->update([
                $idColumn => $socialUser->getId(),
                'last_login_at' => now(),
            ]);

            return $this->sendAuthResult($user, __('auth.login_success'));
        }

        $nickname = $socialUser->getNickname();
        $name = $socialUser->getName();
        $displayName = match (true) {
            $nickname !== null && $nickname !== '' => $nickname,
            $name !== null && $name !== '' => $name,
            default => 'social_user',
        };

        $user = User::create([
            'name' => $displayName,
            'nickname' => $displayName,
            $idColumn => $socialUser->getId(),
            'email' => $socialUser->getEmail(),
            'password' => str()->random(24),
            'avatar' => $socialUser->getAvatar(),
            'last_login_at' => now(),
        ]);

        return $this->sendAuthResult($user, __('auth.login_success'));
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
