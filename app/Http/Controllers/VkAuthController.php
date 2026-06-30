<?php

namespace App\Http\Controllers;

use App\Filament\Pages\SiteSettings;
use App\Services\SettingsService;
use App\Services\VkService;
use App\Support\AccessControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * OAuth-вход во ВКонтакте для получения пользовательского токена,
 * которым автопостинг публикует новости на стене сообщества.
 *
 * Поток: connect() уводит админа на страницу авторизации VK, VK возвращает
 * его на callback() с кодом, который мы меняем на токен и сохраняем в настройки.
 */
class VkAuthController extends Controller
{
    public function connect(Request $request, VkService $vk): RedirectResponse
    {
        $this->authorize();

        if (! $vk->isOAuthConfigured()) {
            return $this->backToSettings('error', 'VK-приложение не настроено: задайте VK_APP_ID и VK_APP_SECRET.');
        }

        return redirect()->away($vk->authorizeUrl($this->redirectUri()));
    }

    public function callback(Request $request, VkService $vk, SettingsService $settings): RedirectResponse
    {
        $this->authorize();

        if ($request->filled('error')) {
            return $this->backToSettings(
                'error',
                'Доступ не предоставлен: ' . $request->string('error_description', $request->string('error')),
            );
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return $this->backToSettings('error', 'VK не вернул код авторизации.');
        }

        $token = $vk->exchangeCodeForToken($code, $this->redirectUri());

        if ($token === null) {
            return $this->backToSettings('error', 'Не удалось получить токен от VK. Попробуйте ещё раз.');
        }

        $settings->set(VkService::TOKEN_SETTING_KEY, $token, 'home');

        return $this->backToSettings('success', 'VK успешно подключён.');
    }

    /**
     * redirect_uri обязан совпадать на обоих шагах OAuth и в настройках приложения VK.
     */
    private function redirectUri(): string
    {
        return URL::route('vk.callback');
    }

    private function authorize(): void
    {
        abort_unless(AccessControl::allows(SiteSettings::class), 403);
    }

    private function backToSettings(string $status, string $message): RedirectResponse
    {
        // Явно указываем панель: контроллер работает вне middleware Filament,
        // поэтому «текущая панель» может быть не определена.
        // Сообщение покажем session-флешем; страница настроек выведет уведомление.
        return redirect(SiteSettings::getUrl(panel: 'admin'))
            ->with("vk_{$status}", $message);
    }
}
