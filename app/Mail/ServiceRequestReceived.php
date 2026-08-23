<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ServiceRequest;
use Illuminate\Mail\Mailable;

/**
 * Копия заявки на услугу — заказчику.
 *
 * ТЗ 3-го этапа (п. 7) требует уведомлять обе стороны: отдел заказов получает
 * ServiceRequested, заказчик — это письмо с тем, что он отправил. Копия важна
 * для конструктора мероприятия: в ней остаётся подобранный флот и расчёт,
 * который на сайте живёт только до перезагрузки страницы.
 */
class ServiceRequestReceived extends Mailable
{
    public function __construct(
        public readonly ServiceRequest $serviceRequest,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Ваша заявка принята: '.$this->serviceRequest->type->label())
            ->markdown('mail.service-request-received');
    }
}
