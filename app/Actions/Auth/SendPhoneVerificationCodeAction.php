<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Services\SmsService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Единая точка отправки кода подтверждения телефона по SMS.
 *
 * Через неё проходят все кнопки «отправить код», поэтому лимиты общие:
 * пауза между отправками (PhoneVerificationCode::RESEND_COOLDOWN_SECONDS)
 * и потолок отправок на пару «номер + IP» за окно.
 *
 * @see SmsService провайдер i-digital direct
 * @see VerifyPhoneCodeAction проверка кода
 */
final class SendPhoneVerificationCodeAction
{
    /** Максимум отправок на пару «номер + IP» в течение окна. */
    private const MAX_ATTEMPTS = 5;

    /** Окно троттлинга, секунд. */
    private const DECAY_SECONDS = 3600;

    /** Текст SMS: до 70 символов — это одно сообщение в кириллице. */
    private const MESSAGE_TEMPLATE = 'Код подтверждения: %s. Действует %d мин.';

    public function __construct(private readonly SmsService $sms) {}

    /**
     * @param  bool  $throttle  false — для отправок, инициированных администратором
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
                'phone' => 'Укажите телефон в профиле и сохраните изменения — код придёт на сохранённый номер.',
            ]);
        }

        $this->ensurePhoneIsNotTaken($user, $phone);

        if (! $this->sms->isConfigured()) {
            throw ValidationException::withMessages([
                'phone' => 'Отправка SMS не настроена. Обратитесь к администратору сайта.',
            ]);
        }

        $this->ensureCooldownPassed($user);

        if ($throttle) {
            $this->ensureIsNotRateLimited($phone);
        }

        // Прежние коды больше не действуют: одновременно живёт только последний.
        $this->expirePreviousCodes($user);

        $plainCode = $this->generateCode();

        $code = PhoneVerificationCode::create([
            'user_id' => $user->getKey(),
            'phone' => $phone,
            'code_hash' => PhoneVerificationCode::hashCode($plainCode),
            'expires_at' => now()->addMinutes(PhoneVerificationCode::TTL_MINUTES),
        ]);

        $result = $this->sms->send(
            $phone,
            sprintf(self::MESSAGE_TEMPLATE, $plainCode, PhoneVerificationCode::TTL_MINUTES),
            externalId: (string) $code->getKey(),
        );

        if (! $result->ok) {
            // Неотправленный код не должен занимать «слот» и блокировать повтор паузой.
            $code->delete();

            Log::warning('Не удалось отправить код подтверждения телефона', [
                'user_id' => $user->getKey(),
                'phone' => PhoneNumber::mask($phone),
                'error' => $result->message(),
            ]);

            throw ValidationException::withMessages([
                'phone' => $result->shouldRetry()
                    ? 'Не удалось отправить SMS. Попробуйте ещё раз через минуту.'
                    : 'Не удалось отправить SMS. Обратитесь к администратору сайта.',
            ]);
        }

        $code->forceFill(['provider_message_id' => $result->messageUuid])->save();

        return $code;
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
                'phone' => "Код уже отправлен. Повторная отправка будет доступна через {$seconds} с.",
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
                'phone' => "Слишком много запросов кода. Попробуйте через {$minutes} мин.",
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

    /** Код фиксированной длины, ведущие нули допустимы. */
    private function generateCode(): string
    {
        $max = (10 ** PhoneVerificationCode::CODE_LENGTH) - 1;

        return str_pad((string) random_int(0, $max), PhoneVerificationCode::CODE_LENGTH, '0', STR_PAD_LEFT);
    }
}
