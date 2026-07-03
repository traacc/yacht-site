<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FilamentAuthenticate
{
    public function handle(Request $request, Closure $next, ...$guards): mixed
    {
        if (! Auth::check()) {
            $loginUrl = $this->loginUrlFor($request);

            // Livewire-запрос (действие на панели): возвращаем ответ-редирект,
            // который клиент выполнит как переход на страницу входа.
            if ($request->hasHeader('X-Livewire')) {
                return redirect($loginUrl);
            }

            // Прочие JSON-запросы (не Livewire) получают стандартный 401.
            if ($request->expectsJson()) {
                throw new AuthenticationException('Unauthenticated.', $guards);
            }

            // Обычная загрузка страницы: запоминаем целевой URL и уводим на вход.
            return redirect()->guest($loginUrl)
                ->with('warning', 'Ваша сессия истекла. Пожалуйста, войдите снова.');
        }

        $panel = Filament::getCurrentPanel();

        if ($panel && ! Auth::user()->canAccessPanel($panel)) {
            abort(403);
        }

        return $next($request);
    }

    /**
     * Определяет страницу входа для текущей панели. Если панель определить не
     * удалось (например, на общем эндпоинте Livewire), берём её из пути
     * заголовка Referer.
     */
    private function loginUrlFor(Request $request): string
    {
        if ($panel = Filament::getCurrentPanel()) {
            return $panel->getLoginUrl();
        }

        $path = ltrim((string) parse_url($request->header('referer', $request->path()), PHP_URL_PATH), '/');

        return str_starts_with($path, 'user')
            ? route('filament.user.auth.login')
            : route('filament.admin.auth.login');
    }
}
