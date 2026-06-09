<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    })->create();
