<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class YandexCaptcha implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Делаем GET-запрос к API Яндекса для проверки токена
        $response = Http::get('https://smartcaptcha.yandexcloud.net/validate', [
            'secret' => config('services.yandex_captcha.server_key'),
            'token' => $value,
            'ip' => request()->ip(), // Передача IP пользователя (опционально, но рекомендуется)
        ]);

        $result = $response->json();

        // Проверяем статус ответа
        if ($response->failed() || !isset($result['status']) || $result['status'] !== 'ok') {
            $fail('Не удалось пройти проверку капчи. Пожалуйста, попробуйте еще раз.');
        }
    }
}
