<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\URL;

class VerifyEmailMail extends Mailable
{
    public function __construct(
        public readonly User $user,
    ) {}

    public function build(): self
    {
        $expire = (int) config('auth.verification.expire', 1440);

        // Формат ссылки должен совпадать с Illuminate\Auth\Notifications\VerifyEmail,
        // иначе стандартный EmailVerificationRequest отклонит её (403).
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($expire),
            [
                'id' => $this->user->getKey(),
                'hash' => sha1($this->user->getEmailForVerification()),
            ],
        );

        return $this
            ->subject('Подтверждение e-mail на сайте Carter Pro')
            ->priority(1)
            ->markdown('mail.verify-email', [
                'url' => $url,
                'expireHours' => (int) round($expire / 60),
            ]);
    }
}
