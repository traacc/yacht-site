<?php

namespace App\Livewire\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke(): \Illuminate\Http\RedirectResponse
    {
        // logoutCurrentDevice() вместо logout(): logout() перегенерирует
        // remember_token в БД, из-за чего remember-куки на всех остальных
        // устройствах аккаунта становятся невалидными — и людей, сидящих
        // под тем же аккаунтом, выбивает. Здесь же удаляем remember-куку
        // только на текущем устройстве.
        $guard = Auth::guard('web');
        $guard->logoutCurrentDevice();
        Cookie::queue(Cookie::forget($guard->getRecallerName()));

        Session::invalidate();
        Session::regenerateToken();

        return redirect('/');
    }
}
