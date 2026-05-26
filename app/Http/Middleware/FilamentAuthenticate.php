<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FilamentAuthenticate
{
    public function handle(Request $request, Closure $next, ...$guards): mixed
    {
        if (Auth::check()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            throw new AuthenticationException('Unauthenticated.', $guards);
        }

        return redirect('/')->with('warning', 'Ваша сессия истекла. Пожалуйста, войдите снова.');
    }
}