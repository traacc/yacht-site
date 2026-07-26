<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Auth\SendEmailVerificationLinkAction;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Баннер в личном кабинете: напоминание подтвердить e-mail
 * (без подтверждения недоступна онлайн-оплата) + повторная отправка письма.
 */
class EmailVerificationBanner extends Component
{
    public bool $sent = false;

    public ?string $error = null;

    public function resend(): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        $this->sent = false;
        $this->error = null;

        try {
            app(SendEmailVerificationLinkAction::class)->handle($user);
        } catch (ValidationException $e) {
            $this->error = collect($e->errors())->flatten()->first();

            return;
        }

        $this->sent = true;
    }

    public function render(): View
    {
        $user = Auth::user();

        // Скрываем для гостей, подтверждённых и «технических» аккаунтов
        // (последним сначала нужно указать настоящий e-mail в профиле).
        $visible = $user instanceof User
            && ! $user->hasVerifiedEmail()
            && ! $user->hasTechnicalEmail();

        return view('filament.user.email-verification-banner', [
            'visible' => $visible,
            'email' => $user?->email,
            'sent' => $this->sent,
            'error' => $this->error,
        ]);
    }
}
