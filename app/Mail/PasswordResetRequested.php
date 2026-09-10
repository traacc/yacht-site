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
            ->markdown('mail.password-reset-requested');
    }
}
