<?php

declare(strict_types=1);

namespace App\Mail;

use App\Filament\Resources\PasswordResetRequests\PasswordResetRequestResource;
use App\Models\PasswordResetRequest;
use Illuminate\Mail\Mailable;

/**
 * Ответ администрации на запрос восстановления пароля.
 *
 * Отправляется вручную из админ-панели на адрес, указанный в форме.
 *
 * @see PasswordResetRequestResource
 */
class PasswordResetRequestAnswered extends Mailable
{
    public function __construct(
        public readonly PasswordResetRequest $request,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Ответ на ваш запрос восстановления пароля')
            ->markdown('mail.password-reset-request-answered');
    }
}
