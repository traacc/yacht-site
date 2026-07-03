<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'filament.auth' => \App\Http\Middleware\FilamentAuthenticate::class,
        ]);
        // Режим обновления применяется только к публичному сайту (группа web).
        // Панель администратора (/admin) использует собственный стек middleware
        // и остаётся доступной для отключения режима.
        $middleware->web(append: [
            \App\Http\Middleware\MaintenanceMode::class,
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
