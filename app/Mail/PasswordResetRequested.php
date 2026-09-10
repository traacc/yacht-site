<?php

declare(strict_types=1);

namespace App\Mail;

use App\Actions\Auth\SubmitPasswordResetRequestAction;
use App\Models\PasswordResetRequest;
use Illuminate\Mail\Mailable;

/**
 * Посетитель запросил восстановление пароля через форму входа.
 *
 * Уходит на адреса из настроек сайта (SettingsService::adminNotificationEmails()).
 *
 * @see SubmitPasswordResetRequestAction
 */
class PasswordResetRequested extends Mailable
{
    public function __construct(
        public readonly PasswordResetRequest $request,
        public readonly string $answerUrl,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Запрос на восстановление пароля: '.$this->request->email)
            // Ответ кнопкой «Ответить» уходит заявителю, а не на служебный ящик.
            // Именно Reply-To, а не From: письма отправляются с домена сайта, и
            // подстановка чужого адреса в From не проходит SPF/DKIM — почта
            // заявителя такое письмо отклонит или положит в спам.
            ->replyTo($this->request->email, $this->request->requesterName())
            ->markdown('mail.password-reset-requested');
    }
}
