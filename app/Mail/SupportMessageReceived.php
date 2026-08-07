<?php

declare(strict_types=1);

namespace App\Mail;

use App\Actions\Chat\SendChatMessageAction;
use App\Filament\Pages\SupportChat;
use App\Models\ChatMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Str;

/**
 * Пользователь написал в поддержку.
 *
 * Уходит на общие адреса администрации из настроек сайта
 * (SettingsService::adminNotificationEmails()) — независимо от того, кто из
 * операторов сидит в панели и какие каналы уведомлений он себе включил.
 *
 * @see SendChatMessageAction
 */
class SupportMessageReceived extends Mailable
{
    public function __construct(
        public readonly ChatMessage $message,
    ) {}

    public function build(): self
    {
        $subject = $this->message->body !== null && $this->message->body !== ''
            ? Str::limit($this->message->body, 60)
            : ($this->message->conversation?->title ?? 'без темы');

        return $this
            ->subject('Сообщение в поддержку: '.$subject)
            ->markdown('mail.support-message-received', [
                'conversation' => $this->message->conversation,
                'attachmentsCount' => $this->message->getMedia(ChatMessage::ATTACHMENTS)->count(),
                // Панель указываем явно: письмо отправляется из контекста
                // публичного сайта, где текущей панели нет.
                'answerUrl' => SupportChat::getUrl(panel: 'admin'),
            ]);
    }
}
