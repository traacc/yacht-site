<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Filament\User\Resources\Questions\QuestionResource;
use App\Observers\UserQuestionObserver;
use Illuminate\Support\Str;

/**
 * Администрация ответила на вопрос пользователя.
 *
 * @see UserQuestionObserver
 */
final class QuestionAnsweredNotification extends UserNotification
{
    public function __construct(
        public readonly string $question,
        public readonly string $answer,
    ) {}

    public function category(): NotificationCategory
    {
        return NotificationCategory::SiteRequests;
    }

    public function title(): string
    {
        return 'Получен ответ на ваш вопрос';
    }

    public function body(): string
    {
        return 'Вопрос: '.Str::limit($this->question, 200)
            ."\n\n".'Ответ: '.Str::limit($this->answer, 600);
    }

    public function url(): ?string
    {
        // Ведём в «Мои вопросы», где виден и сам вопрос, и ответ.
        // Панель указываем явно: уведомление отправляется из очереди, где текущей
        // панели нет, и getUrl() собрал бы несуществующий маршрут.
        return QuestionResource::getUrl(panel: 'user');
    }

    public function icon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }
}
