<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Regatta;
use App\Models\User;
use Illuminate\Mail\Mailable;

class SendRegattaEntryPassword extends Mailable
{
    public function __construct(
        public readonly User $captain,
        public readonly Regatta $regatta,
        public readonly string $entryPassword,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Пароль заявки на регату «' . $this->regatta->name . '»')
            ->markdown('mail.send-regatta-entry-password');
    }
}
