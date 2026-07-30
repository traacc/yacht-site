<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\RepairRequest;
use Illuminate\Mail\Mailable;

class RepairRequested extends Mailable
{
    public function __construct(
        public readonly RepairRequest $repairRequest,
        public readonly string $adminUrl,
    ) {}

    public function build(): self
    {
        // Тему формирует модель: по ТЗ она должна называть конкретный кейс,
        // с чьей страницы нажали «Хотите такой ремонт?».
        return $this
            ->subject($this->repairRequest->mailSubject())
            ->priority(1)
            ->markdown('mail.repair-requested');
    }
}
