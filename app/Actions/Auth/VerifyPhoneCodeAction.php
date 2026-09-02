<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Проверяет код из звонка (последние цифры номера звонящего)
 * и отмечает телефон подтверждённым.
 *
 * Попытки считаются на самом коде: после MAX_ATTEMPTS промахов код сгорает
 * и нужен новый звонок — перебор четырёх цифр становится бессмысленным.
 *
 * @see RequestPhoneVerificationCallAction
 */
final class VerifyPhoneCodeAction
{
    /** @throws ValidationException */
    public function handle(User $user, string $plainCode): void
    {
        if ($user->hasVerifiedPhone()) {
            throw ValidationException::withMessages([
                'code' => 'Телефон уже подтверждён.',
            ]);
        }

        $phone = $user->normalizedPhone();

        $code = $phone === null ? null : PhoneVerificationCode::query()
            ->where('user_id', $user->getKey())
            // Номер мог смениться после звонка — старый код к нему не подходит.
            ->where('phone', $phone)
            ->usable()
            ->latest()
            ->first();

        if ($code === null) {
            throw ValidationException::withMessages([
                'code' => 'Код не найден или устарел. Закажите новый звонок.',
            ]);
        }

        $entered = preg_replace('/\D+/', '', $plainCode) ?? '';

        if (! $this->matches($code, $entered)) {
            $code->increment('attempts');

            // Разбирать жалобы «ввожу правильный код» без этого невозможно:
            // сам код не логируем, только длину и звонок у провайдера.
            Log::warning('Код подтверждения телефона не совпал', [
                'user_id' => $user->getKey(),
                'call_id' => $code->provider_call_id,
                'entered_length' => mb_strlen($entered),
                'expected_length' => $code->expectedLength(),
                'attempts' => $code->attempts,
            ]);

            $left = max(0, PhoneVerificationCode::MAX_ATTEMPTS - $code->attempts);

            throw ValidationException::withMessages([
                'code' => $left > 0
                    ? "Неверный код. Осталось попыток: {$left}."
                    : 'Неверный код, попытки исчерпаны. Закажите новый звонок.',
            ]);
        }

        DB::transaction(function () use ($user, $code): void {
            $code->forceFill(['confirmed_at' => now()])->save();

            $user->markPhoneAsVerified();

            // Остальные невыясненные коды пользователя больше не нужны.
            PhoneVerificationCode::query()
                ->where('user_id', $user->getKey())
                ->whereKeyNot($code->getKey())
                ->whereNull('confirmed_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);
        });
    }

    /**
     * Сверяет ввод с кодом, не придираясь к ведущим нулям.
     *
     * Ноль перед кодом теряется везде, где код успевает побывать числом:
     * в поле-«числе», в JSON провайдера, в выгрузках. Поэтому «0421» и «421»
     * считаем одним кодом — сравнение остаётся точным, перебор не облегчается.
     */
    private function matches(PhoneVerificationCode $code, string $entered): bool
    {
        if ($entered === '') {
            return false;
        }

        $candidates = [
            $entered,
            str_pad($entered, $code->expectedLength(), '0', STR_PAD_LEFT),
            ltrim($entered, '0'),
        ];

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate !== '' && hash_equals($code->code_hash, PhoneVerificationCode::hashCode($candidate))) {
                return true;
            }
        }

        return false;
    }
}
