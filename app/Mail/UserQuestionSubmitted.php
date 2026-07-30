<?php

declare(strict_types=1);

namespace App\Mail;

use App\Actions\Question\SubmitUserQuestionAction;
use App\Filament\Resources\UserQuestionResource;
use App\Models\UserQuestion;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Str;

/**
 * Пользователь задал вопрос администрации.
 *
 * Уходит на адреса из настроек сайта (SettingsService::adminNotificationEmails()).
 *
 * @see SubmitUserQuestionAction
 */
class UserQuestionSubmitted extends Mailable
{
    public function __construct(
        public readonly UserQuestion $userQuestion,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Новый вопрос с сайта: '.Str::limit($this->userQuestion->question, 60))
            ->markdown('mail.user-question-submitted', [
                // Панель указываем явно: письмо может отправляться из очереди или
                // консоли, где текущей панели нет.
                'answerUrl' => UserQuestionResource::getUrl(panel: 'admin'),
            ]);
    }
}
