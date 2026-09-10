<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PasswordResetRequested extends Mailable
{
    public function __construct(
        public readonly string $email,
        public readonly string $phone,
        /** ФИО из профиля, если пользователь с таким email зарегистрирован. */
        public readonly ?string $name = null,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Запрос на восстановление пароля: '.$this->email)
            ->markdown('mail.password-reset-requested');
    }
}
