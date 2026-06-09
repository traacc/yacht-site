<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;

class ResetPasswordMail extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly string $token,
    ) {}

    public function build(): self
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $this->user->getEmailForPasswordReset(),
        ]);

        return $this
            ->subject('Восстановление пароля на сайте Carter Pro')
            ->markdown('mail.reset-password', [
                'url' => $url,
                'expire' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            ]);
    }
}
