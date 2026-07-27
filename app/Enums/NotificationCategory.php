<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Notifications\NotificationPreferences;
use Filament\Support\Contracts\HasLabel;

/**
 * Категория уведомлений («сервис» в терминах ТЗ). Пользователь в личном кабинете
 * выбирает для каждой категории каналы доставки; отсутствие настройки = включено.
 *
 * @see NotificationPreferences
 */
enum NotificationCategory: string implements HasLabel
{
    case SiteRequests = 'site_requests';
    case Important = 'important';
    case ChatMessages = 'chat_messages';
    case News = 'news';

    public function getLabel(): string
    {
        return match ($this) {
            self::SiteRequests => 'Запросы с сайта',
            self::Important => 'Важная информация',
            self::ChatMessages => 'Сообщения чата',
            self::News => 'Анонсы и новости',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SiteRequests => 'Ответы на ваши вопросы, отклики на объявления, заявки на аренду.',
            self::Important => 'Изменения в расписании, решения оргкомитета, регламенты.',
            self::ChatMessages => 'Новые сообщения в чатах.',
            self::News => 'Анонсы регат и новости ассоциации.',
        };
    }
}
