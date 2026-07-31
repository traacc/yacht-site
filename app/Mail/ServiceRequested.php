<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ServiceRequest;
use Illuminate\Mail\Mailable;

class ServiceRequested extends Mailable
{
    public function __construct(
        public readonly ServiceRequest $serviceRequest,
        public readonly string $adminUrl,
    ) {}

    public function build(): self
    {
        // Тему формирует модель: по ТЗ она должна называть конкретную услугу.
        return $this
            ->subject($this->serviceRequest->mailSubject())
            ->priority(1)
            ->markdown('mail.service-requested');
    }
}
