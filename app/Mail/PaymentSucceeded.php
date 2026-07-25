<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\PaymentTransaction;
use Illuminate\Mail\Mailable;

class PaymentSucceeded extends Mailable
{
    public function __construct(
        public readonly PaymentTransaction $transaction,
    ) {}

    public function build(): self
    {
        $this->transaction->loadMissing(['registry', 'user']);

        return $this
            ->subject('Оплата получена: '.($this->transaction->registry?->name ?? 'платёж'))
            ->markdown('mail.payment-succeeded');
    }
}
