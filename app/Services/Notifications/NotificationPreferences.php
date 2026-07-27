<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\User;

/**
 * Резолвер настроек уведомлений.
 *
 * Главное правило: отсутствие строки в notification_preferences означает
 * «включено». Благодаря этому все ранее зарегистрированные пользователи
 * получают всё по умолчанию (требование ТЗ) без миграции данных.
 *
 * Регистрируется синглтоном, поэтому мемоизация живёт весь запрос или всю
 * очередную job — при массовой рассылке это экономит запрос на пользователя.
 */
class NotificationPreferences
{
    /** @var array<string, array<string, array<string, bool>>> user_id => категория => канал => bool */
    private array $cache = [];

    /**
     * Матрица для формы личного кабинета.
     *
     * @return array<string, array<string, bool>>
     */
    public function matrix(User $user): array
    {
        $stored = $this->stored($user);
        $matrix = [];

        foreach (NotificationCategory::cases() as $category) {
            foreach (NotificationChannel::available() as $channel) {
                $matrix[$category->value][$channel->value] =
                    $stored[$category->value][$channel->value] ?? true;
            }
        }

        return $matrix;
    }

    public function isEnabled(User $user, NotificationCategory $category, NotificationChannel $channel): bool
    {
        return $this->stored($user)[$category->value][$channel->value] ?? true;
    }

    /**
     * Каналы, по которым уведомление этой категории реально дойдёт до пользователя:
     * канал реализован, включён пользователем и технически доставим.
     *
     * @return list<NotificationChannel>
     */
    public function channelsFor(User $user, NotificationCategory $category): array
    {
        return array_values(array_filter(
            NotificationChannel::available(),
            fn (NotificationChannel $channel): bool => $this->isEnabled($user, $category, $channel)
                && $this->isDeliverable($user, $channel),
        ));
    }

    /** Сбрасывает мемоизацию после сохранения настроек. */
    public function forget(?User $user = null): void
    {
        if ($user === null) {
            $this->cache = [];

            return;
        }

        unset($this->cache[(string) $user->getKey()]);
    }

    private function isDeliverable(User $user, NotificationChannel $channel): bool
    {
        return match ($channel) {
            // Технические адреса @noemail.local (гостевые заявки) письмами не беспокоим.
            NotificationChannel::Email => ! $user->hasTechnicalEmail(),
            NotificationChannel::Telegram => $user->hasLinkedTelegram(),
            default => true,
        };
    }

    /**
     * Сохранённые настройки пользователя. Если связь уже загружена (массовая
     * рассылка грузит её через with()), запросов не будет вовсе.
     *
     * @return array<string, array<string, bool>>
     */
    private function stored(User $user): array
    {
        return $this->cache[(string) $user->getKey()] ??= $user->notificationPreferences
            ->reduce(function (array $carry, NotificationPreference $preference): array {
                $carry[$preference->category->value][$preference->channel->value] = $preference->enabled;

                return $carry;
            }, []);
    }
}
