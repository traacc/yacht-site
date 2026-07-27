<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\User;
use App\Services\Notifications\NotificationPreferences;

/**
 * Отписка по ссылке из письма: выключает категорию для одного канала
 * или, если канал не указан, целиком.
 */
final class UnsubscribeAction
{
    public function __construct(
        private readonly NotificationPreferences $preferences,
        private readonly SaveNotificationPreferencesAction $save,
    ) {}

    public function handle(User $user, NotificationCategory $category, ?NotificationChannel $channel = null): void
    {
        $matrix = $this->preferences->matrix($user);

        foreach (NotificationChannel::available() as $available) {
            if ($channel === null || $channel === $available) {
                $matrix[$category->value][$available->value] = false;
            }
        }

        $this->save->handle($user, $matrix);
    }
}
