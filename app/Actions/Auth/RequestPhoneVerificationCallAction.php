<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Services\FlashCallService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Единая точка заказа звонка-подтверждения телефона (Flash Call).
 *
 * Через неё проходят все кнопки «позвонить», поэтому лимиты общие:
 * пауза между звонками (PhoneVerificationCode::RESEND_COOLDOWN_SECONDS)
 * и потолок звонков на пару «номер + IP» за окно.
 *
 * Код не генерируется на сайте: провайдер звонит с номера, последние цифры
 * которого и есть код, и возвращает их в ответе — сохраняем только хеш.
 *
 * @see FlashCallService сервис «Звонок» (zvonok.com)
 * @see VerifyPhoneCodeAction проверка кода
 */
final class RequestPhoneVerificationCallAction
{
    /** Максимум звонков на пару «номер + IP» в течение окна. */
    private const MAX_ATTEMPTS = 5;

    /** Окно троттлинга, секунд. */
    private const DECAY_SECONDS = 3600;

    public function __construct(private readonly FlashCallService $flashCall) {}

    /**
     * @param  bool  $throttle  false — для звонков, инициированных администратором
     *
     * @throws ValidationException
     */
    public function handle(User $user, bool $throttle = true): PhoneVerificationCode
    {
        if ($user->hasVerifiedPhone()) {
            throw ValidationException::withMessages([
                'phone' => 'Телефон уже подтверждён.',
            ]);
        }

        $phone = $user->normalizedPhone();

        if ($phone === null) {
            throw ValidationException::withMessages([
                'phone' => 'Укажите телефон в профиле и сохраните изменения — звонок поступит на сохранённый номер.',
            ]);
        }

        $this->ensurePhoneIsNotTaken($user, $phone);

        if (! $this->flashCall->isConfigured()) {
            throw ValidationException::withMessages([
                'phone' => 'Подтверждение звонком не настроено. Обратитесь к администратору сайта.',
            ]);
        }

        $this->ensureCooldownPassed($user);

        if ($throttle) {
            $this->ensureIsNotRateLimited($phone);
        }

        $result = $this->flashCall->call($phone);

        if (! $result->ok) {
            Log::warning('Не удалось заказать звонок для подтверждения телефона', [
                'user_id' => $user->getKey(),
                'phone' => PhoneNumber::mask($phone),
                'error' => $result->message(),
            ]);

            throw ValidationException::withMessages([
                'phone' => $result->shouldRetry()
                    ? 'Не удалось заказать звонок. Попробуйте ещё раз через минуту.'
                    : 'Не удалось заказать звонок. Обратитесь к администратору сайта.',
            ]);
        }

        // Прежние коды больше не действуют: одновременно живёт только последний.
        // Гасим их после успешного звонка, иначе неудачная попытка обнулила бы
        // ещё годный код.
        $this->expirePreviousCodes($user);

        $pincode = (string) $result->pincode();

        return PhoneVerificationCode::create([
            'user_id' => $user->getKey(),
            'phone' => $phone,
            'code_hash' => PhoneVerificationCode::hashCode($pincode),
            // Сколько цифр просить у пользователя, решает кампания провайдера,
            // а не константа сайта.
            'code_length' => mb_strlen($pincode),
            'expires_at' => now()->addMinutes(PhoneVerificationCode::TTL_MINUTES),
            'provider_call_id' => $result->callId(),
        ]);
    }

    /**
     * Один номер — один подтверждённый аккаунт: иначе подтверждение
     * перестаёт быть признаком владения номером.
     *
     * @throws ValidationException
     */
    private function ensurePhoneIsNotTaken(User $user, string $phone): void
    {
        // Номер в users.phone хранится в маске, но у импортированных аккаунтов
        // написание бывает другим, поэтому сравниваем нормализованные цифры.
        // Выборка узкая: только уже подтверждённые номера.
        $taken = User::query()
            ->whereKeyNot($user->getKey())
            ->whereNotNull('phone_verified_at')
            ->pluck('phone')
            ->contains(static fn (?string $stored): bool => PhoneNumber::normalize($stored) === $phone);

        if ($taken) {
            throw ValidationException::withMessages([
                'phone' => 'Этот номер уже подтверждён другим аккаунтом.',
            ]);
        }
    }

    /** @throws ValidationException */
    private function ensureCooldownPassed(User $user): void
    {
        $last = PhoneVerificationCode::query()
            ->where('user_id', $user->getKey())
            ->latest()
            ->first();

        $seconds = $last?->secondsUntilResend() ?? 0;

        if ($seconds > 0) {
            throw ValidationException::withMessages([
                'phone' => "Звонок уже заказан. Повторный будет доступен через {$seconds} с.",
            ]);
        }
    }

    /** @throws ValidationException */
    private function ensureIsNotRateLimited(string $phone): void
    {
        $key = 'verify-phone|'.$phone.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            throw ValidationException::withMessages([
                'phone' => "Слишком много запросов звонка. Попробуйте через {$minutes} мин.",
            ]);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);
    }

    private function expirePreviousCodes(User $user): void
    {
        PhoneVerificationCode::query()
            ->where('user_id', $user->getKey())
            ->whereNull('confirmed_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);
    }
}
