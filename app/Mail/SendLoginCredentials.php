<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;

class SendLoginCredentials extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly string $email,
        public readonly string $password,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Ваши данные для входа на сайт Carter Pro')
            ->markdown('mail.send-login-credentials');
    }
}
