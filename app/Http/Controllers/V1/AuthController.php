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
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\ResponseFromFile;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Tymon\JWTAuth\JWTGuard;

#[Group('用户认证')]
final class AuthController extends AppBaseController
{
    public function __construct(
        protected WechatService $wechatService,
        protected AuthFlowService $authFlowService,
    ) {}

    #[Endpoint(
        '兼容登录入口',
        '兼容使用昵称、邮箱或手机号搭配密码进行登录。成功时直接返回访问令牌，高风险时返回二次验证挑战。',
    )]
    #[Unauthenticated]
    #[ResponseField('data.access_token', 'string', '访问令牌')]
    #[ResponseField('data.user.id', 'int', '用户 ID')]
    #[ResponseField('data.user.nickname', 'string', '用户昵称')]
    #[ResponseField('data.status', 'string', '登录挑战状态')]
    #[ResponseField('data.challenge_token', 'string', '登录挑战令牌')]
    #[ResponseFromFile('storage/responses/v1/auth/auth-result.json')]
    #[ResponseFromFile('storage/responses/v1/auth/auth-challenge-login.json', status: 200)]
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

    #[Endpoint('发起登录', '发起邮箱密码登录请求。登录成功时返回访问令牌；需要二次验证时返回验证码挑战信息。')]
    #[Unauthenticated]
    #[ResponseField('data.access_token', 'string', '访问令牌')]
    #[ResponseField('data.user.id', 'int', '用户 ID')]
    #[ResponseField('data.user.nickname', 'string', '用户昵称')]
    #[ResponseField('data.status', 'string', '登录挑战状态')]
    #[ResponseField('data.challenge_token', 'string', '登录挑战令牌')]
    #[ResponseFromFile('storage/responses/v1/auth/auth-result.json')]
    #[ResponseFromFile('storage/responses/v1/auth/auth-challenge-login.json', status: 200)]
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

    #[Endpoint('提交登录验证码挑战', '提交登录挑战令牌与 6 位邮箱验证码，完成二次验证并获取登录访问令牌。')]
    #[Unauthenticated]
    #[ResponseField('data.access_token', 'string', '访问令牌')]
    #[ResponseField('data.user.id', 'int', '用户 ID')]
    #[ResponseField('data.user.nickname', 'string', '用户昵称')]
    #[ResponseFromFile('storage/responses/v1/auth/auth-result.json')]
    public function signinVerify(SigninVerifyRequest $request): JsonResponse
    {
        $user = $this->authFlowService->verifySignin(
            challengeToken: $request->string('challenge_token')->toString(),
            code: $request->string('code')->toString(),
            ip: $request->ip(),
        );

        return $this->sendAuthResult($user, __('auth.login_success'));
    }

    #[Endpoint('兼容注册入口', '发起邮箱注册请求并发送验证码（兼容旧注册入口）。')]
    #[Unauthenticated]
    #[ResponseField('data.status', 'string', '验证码发送状态')]
    #[ResponseField('data.email', 'string', '目标邮箱')]
    #[ResponseField('data.challenge_token', 'string', '验证挑战令牌')]
    #[ResponseFromFile('storage/responses/v1/auth/auth-challenge-register.json')]
    public function register(SignupRequest $request): JsonResponse
    {
        return $this->signupRequest($request);
    }

    #[Endpoint('发起注册并发送邮箱验证码', '校验注册邮箱与密码，并向目标邮箱发送 6 位注册验证码。')]
    #[Unauthenticated]
    #[ResponseField('data.status', 'string', '验证码发送状态')]
    #[ResponseField('data.email', 'string', '目标邮箱')]
    #[ResponseField('data.challenge_token', 'string', '验证挑战令牌')]
    #[ResponseFromFile('storage/responses/v1/auth/auth-challenge-register.json')]
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

    #[Endpoint('验证注册邮箱验证码并创建账号', '验证 6 位邮箱验证码，完成用户账号创建并签发访问令牌。')]
    #[Unauthenticated]
    #[ResponseField('data.access_token', 'string', '访问令牌')]
    #[ResponseField('data.user.id', 'int', '用户 ID')]
    #[ResponseField('data.user.email', 'string', '用户邮箱')]
    #[ResponseFromFile('storage/responses/v1/auth/auth-result.json')]
    public function signupVerify(SignupVerifyRequest $request): JsonResponse
    {
        $user = $this->authFlowService->verifySignup(
            email: $request->string('email')->toString(),
            code: $request->string('code')->toString(),
            ip: $request->ip(),
        );

        return $this->sendAuthResult($user, __('auth.register_success'));
    }

    #[Endpoint('重发验证码', '为注册、登录或找回密码流程重发新的邮箱验证码（设有 60 秒冷却限流）。')]
    #[Unauthenticated]
    #[ResponseField('data.status', 'string', '验证码发送状态')]
    #[ResponseField('data.challenge_token', 'string', '验证挑战令牌')]
    #[ResponseFromFile('storage/responses/v1/auth/auth-challenge-login.json')]
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

    #[Endpoint('发起忘记密码', '向绑定邮箱发送 6 位重置密码验证码。')]
    #[Unauthenticated]
    #[ResponseField('data.status', 'string', '验证码发送状态')]
    #[ResponseField('data.challenge_token', 'string', '验证挑战令牌')]
    #[ResponseFromFile('storage/responses/v1/auth/auth-challenge-reset-password.json')]
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

    #[Endpoint('使用验证码重置密码', '提交邮箱、验证码与新密码，完成密码重置。')]
    #[Unauthenticated]
    #[ResponseField('data.status', 'string', '重置结果状态')]
    #[ResponseFromFile('storage/responses/v1/auth/reset-password.json')]
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

    #[Endpoint('重定向至第三方登录 (OAuth)', '获取第三方平台（如 GitHub、Google 等）授权重定向地址。')]
    #[Unauthenticated]
    #[UrlParam('provider', 'string', '第三方平台标识', required: true, example: 'github')]
    #[ResponseField('url', 'string', '第三方授权跳转地址')]
    #[ResponseFromFile('storage/responses/v1/auth/provider-redirect.json')]
    public function redirectToProvider(string $provider): JsonResponse
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        return response()->json([
            'url' => $driver->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    #[Endpoint('处理第三方登录回调', '接收第三方 OAuth 授权回调，创建或关联系统账号并登录。')]
    #[Unauthenticated]
    #[UrlParam('provider', 'string', '第三方平台标识', required: true, example: 'github')]
    #[ResponseField('data.access_token', 'string', '访问令牌')]
    #[ResponseField('data.user.id', 'int', '用户 ID')]
    #[ResponseField('data.user.nickname', 'string', '用户昵称')]
    #[ResponseField('data.user.email', 'string', '用户邮箱')]
    #[ResponseFromFile('storage/responses/v1/auth/auth-result.json')]
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
