<?php

declare(strict_types=1);

namespace App\Filament\Auth\Pages;

use App\Models\AdminUser;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): TextInput
    {
        return TextInput::make('email')
            ->label(__('auth.login_identifier'))
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        $authProvider = $authGuard->getProvider(); /** @phpstan-ignore-line */
        $loginIdentifier = mb_trim((string) ($data['email'] ?? ''));
        $user = $this->resolveLoginUser($loginIdentifier);

        if (
            (! $user)
            || (! $authProvider->validateCredentials($user, ['password' => $data['password']]))
        ) {
            $this->fireFailedEvent($authGuard, $user, [
                'email' => $loginIdentifier,
            ]);

            $this->throwFailureValidationException();
        }

        if (
            ($user instanceof FilamentUser)
            && (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel()))
        ) {
            $this->fireFailedEvent($authGuard, $user, [
                'email' => $loginIdentifier,
            ]);

            $this->throwFailureValidationException();
        }

        $authGuard->login($user, (bool) ($data['remember'] ?? false));

        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        return Notification::make()
            ->title(__('filament-panels::auth/pages/login.notifications.throttled.title', [
                'seconds' => $exception->secondsUntilAvailable,
                'minutes' => $exception->minutesUntilAvailable,
            ]))
            ->body(array_key_exists('body', __('filament-panels::auth/pages/login.notifications.throttled') ?: []) ? __('filament-panels::auth/pages/login.notifications.throttled.body', [
                'seconds' => $exception->secondsUntilAvailable,
                'minutes' => $exception->minutesUntilAvailable,
            ]) : null)
            ->danger();
    }

    protected function resolveLoginUser(string $loginIdentifier): ?Authenticatable
    {
        $normalizedIdentifier = Str::lower($loginIdentifier);

        if ($normalizedIdentifier === '') {
            return null;
        }

        $query = AdminUser::query();

        if (filter_var($loginIdentifier, FILTER_VALIDATE_EMAIL)) {
            return $query
                ->whereRaw('LOWER(email) = ?', [$normalizedIdentifier])
                ->first();
        }

        return $query
            ->whereRaw('LOWER(username) = ?', [$normalizedIdentifier])
            ->first();
    }
}
