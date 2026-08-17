<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;

class YandexCaptcha implements ValidationRule
{
    /**
     * Капча включена, только когда заданы оба ключа: без публичного ключа
     * виджет не отрисовывается, без приватного нечем проверить токен.
     */
    public static function enabled(): bool
    {
        return filled(config('services.yandex_captcha.site_key'))
            && filled(config('services.yandex_captcha.server_key'));
    }

    /**
     * Правила для поля с токеном капчи. Если ключей в окружении нет,
     * проверка выключается целиком — иначе форму невозможно отправить:
     * виджета на странице нет и токен всегда пустой.
     *
     * @return array<int, string|ValidationRule>
     */
    public static function rules(): array
    {
        return self::enabled()
            ? ['required', 'string', new self]
            : ['nullable'];
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::enabled()) {
            return;
        }

        try {
            // Делаем GET-запрос к API Яндекса для проверки токена
            $response = Http::timeout(5)->get('https://smartcaptcha.yandexcloud.net/validate', [
                'secret' => config('services.yandex_captcha.server_key'),
                'token' => $value,
                'ip' => request()->ip(), // Передача IP пользователя (опционально, но рекомендуется)
            ]);
        } catch (ConnectionException $e) {
            // Сервис недоступен — пропускать проверку нельзя, но сообщение
            // должно объяснять, что дело не в пользователе.
            report($e);
            $fail('Сервис проверки капчи временно недоступен. Пожалуйста, попробуйте позже.');

            return;
        }

        $result = $response->json();

        // Проверяем статус ответа
        if ($response->failed() || ! isset($result['status']) || $result['status'] !== 'ok') {
            $fail('Не удалось пройти проверку капчи. Пожалуйста, попробуйте еще раз.');
        }
    }
}
