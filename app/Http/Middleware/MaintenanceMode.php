<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $enabled = (bool) $this->settings->get('home.maintenance_mode', false);

        // Администраторы продолжают видеть сайт, чтобы иметь возможность
        // отключить режим обновления и проверить страницы.
        $user = Auth::user();

        if (! $enabled || ($user && $user->isAdmin())) {
            return $next($request);
        }

        $message = $this->settings->get('home.maintenance_message', 'Сайт в процессе обновления');

        return response()
            ->view('pages.maintenance', ['message' => $message], 503)
            ->header('Retry-After', '3600');
    }
}
