<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $wasVerified = $request->user()->hasVerifiedEmail();

        if (! $wasVerified && $request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        $title = $wasVerified ? 'E-mail уже был подтверждён' : 'E-mail подтверждён';
        $body = 'Теперь доступна онлайн-оплата взносов и услуг.';

        // Уведомление Filament переживает редирект через сессию и покажется
        // в личном кабинете; session('warning') — для страниц публичного лейаута.
        Notification::make()
            ->title($title)
            ->body($body)
            ->success()
            ->send();

        return redirect()
            ->intended(route('filament.user.home', absolute: false))
            ->with('warning', $title.'. '.$body);
    }
}
