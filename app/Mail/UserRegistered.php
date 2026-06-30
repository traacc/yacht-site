<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;

class UserRegistered extends Mailable
{
    public function __construct(
        public readonly User $user,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Зарегистрирован новый пользователь: ' . $this->user->name)
            ->markdown('mail.user-registered');
    }
}
