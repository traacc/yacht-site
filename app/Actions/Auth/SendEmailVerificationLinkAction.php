<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Единая точка отправки письма для подтверждения e-mail.
 *
 * Через неё проходят все кнопки «отправить ещё раз» (баннер в ЛК, страница
 * verification.notice, экран поданной заявки), поэтому лимит отправок общий.
 */
final class SendEmailVerificationLinkAction
{
    /** Максимум отправок на пару «e-mail + IP» в течение окна. */
    private const MAX_ATTEMPTS = 3;

    /** Окно троттлинга, секунд. */
    private const DECAY_SECONDS = 600;

    /**
     * @param  bool  $throttle  false — для автоматических отправок
     *                          (регистрация, гостевая заявка, смена e-mail).
     *
     * @throws ValidationException
     */
    public function handle(User $user, bool $throttle = true): void
    {
        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => 'E-mail уже подтверждён.',
            ]);
        }

        if ($user->hasTechnicalEmail()) {
            throw ValidationException::withMessages([
                'email' => 'У аккаунта не указан настоящий e-mail. Укажите его в личном кабинете, в разделе «Профиль».',
            ]);
        }

        if ($throttle) {
            $this->ensureIsNotRateLimited($user);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            Log::warning('Не удалось отправить письмо для подтверждения e-mail', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'Не удалось отправить письмо. Попробуйте позже.',
            ]);
        }
    }

    /** @throws ValidationException */
    private function ensureIsNotRateLimited(User $user): void
    {
        $key = $this->throttleKey($user);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => (int) ceil($seconds / 60),
                ]),
            ]);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);
    }

    private function throttleKey(User $user): string
    {
        return 'verify-email|'.Str::transliterate(Str::lower((string) $user->email)).'|'.request()->ip();
    }
}
