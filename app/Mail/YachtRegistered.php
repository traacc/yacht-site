<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Yacht;
use Illuminate\Mail\Mailable;

class YachtRegistered extends Mailable
{
    public function __construct(
        public readonly Yacht $yacht,
    ) {}

    public function build(): self
    {
        $this->yacht->loadMissing('user');

        return $this
            ->subject('Зарегистрирована новая яхта: ' . $this->yacht->name)
            ->markdown('mail.yacht-registered');
    }
}
