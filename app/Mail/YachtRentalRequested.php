<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\YachtRentalRequest;
use Illuminate\Mail\Mailable;

class YachtRentalRequested extends Mailable
{
    public function __construct(
        public readonly YachtRentalRequest $rentalRequest,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Важно: новый запрос на аренду яхты: ' . ($this->rentalRequest->yacht?->name ?? '—'))
            ->priority(1)
            ->markdown('mail.yacht-rental-requested');
    }
}
