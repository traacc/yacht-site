<?php

use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Middleware\MaintenanceMode;
use App\Http\Middleware\VerifyApiToken;
use App\Http\Middleware\VerifyTelegramWebhook;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'filament.auth' => FilamentAuthenticate::class,
            'api.token' => VerifyApiToken::class,
            'telegram.webhook' => VerifyTelegramWebhook::class,
        ]);
        // Режим обновления применяется только к публичному сайту (группа web).
        // Панель администратора (/admin) использует собственный стек middleware
        // и остаётся доступной для отключения режима.
        $middleware->web(append: [
            MaintenanceMode::class,
        ]);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Истёкшая сессия на панели проявляется как несовпадение CSRF-токена
        // (419) при Livewire-действии. Вместо диалога «страница устарела»
        // уводим пользователя на страницу входа нужной панели.
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            $path = ltrim((string) parse_url($request->header('referer', $request->path()), PHP_URL_PATH), '/');

            $loginUrl = str_starts_with($path, 'user')
                ? route('filament.user.auth.login')
                : (str_starts_with($path, 'admin')
                    ? route('filament.admin.auth.login')
                    : route('login'));

            // Livewire выполнит клиентский переход, получив ответ-редирект.
            if ($request->hasHeader('X-Livewire')) {
                return redirect($loginUrl);
            }

            return redirect()->guest($loginUrl)
                ->with('warning', 'Ваша сессия истекла. Пожалуйста, войдите снова.');
        });
    })->create();
