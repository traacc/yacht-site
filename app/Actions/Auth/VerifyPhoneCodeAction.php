<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Проверяет код из SMS и отмечает телефон подтверждённым.
 *
 * Попытки считаются на самом коде: после MAX_ATTEMPTS промахов код сгорает
 * и нужен новый — перебор шестизначного кода становится бессмысленным.
 *
 * @see SendPhoneVerificationCodeAction
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
            // Номер мог смениться после отправки — старый код к нему не подходит.
            ->where('phone', $phone)
            ->usable()
            ->latest()
            ->first();

        if ($code === null) {
            throw ValidationException::withMessages([
                'code' => 'Код не найден или устарел. Запросите новый.',
            ]);
        }

        $entered = preg_replace('/\D+/', '', $plainCode) ?? '';

        if (! hash_equals($code->code_hash, PhoneVerificationCode::hashCode($entered))) {
            $code->increment('attempts');

            $left = max(0, PhoneVerificationCode::MAX_ATTEMPTS - $code->attempts);

            throw ValidationException::withMessages([
                'code' => $left > 0
                    ? "Неверный код. Осталось попыток: {$left}."
                    : 'Неверный код, попытки исчерпаны. Запросите новый код.',
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
}
