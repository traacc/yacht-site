<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

use Illuminate\Support\Facades\Http;

class YandexCaptcha implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            $fail('Вам необходимо пройти проверку на бота.');
            return;
        }

        // Делаем GET-запрос к API Яндекса для проверки токена
        $response = Http::get('https://smartcaptcha.yandexcloud.net/validate', [
            'secret' => config('services.yandex_captcha.server_key'),
            'token'  => $value,
            'ip'     => request()->ip(),
        ]);

        $result = $response->json();

        \Illuminate\Support\Facades\Log::debug('YandexCaptcha validate', [
            'attribute' => $attribute,
            'token_length' => strlen($value),
            'status' => $response->status(),
            'result' => $result,
        ]);

        // Проверяем статус ответа
        if ($response->failed() || !isset($result['status']) || $result['status'] !== 'ok') {
            $fail('Не удалось пройти проверку капчи. Пожалуйста, попробуйте еще раз.');
        }
    }
}
