<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Support\Str;

/**
 * Сохраняет матрицу настроек уведомлений пользователя.
 *
 * Пишем все пары «категория + канал» одним upsert'ом: так состояние
 * пользователя видно в БД целиком, а не только отличия от значений по умолчанию.
 */
final class SaveNotificationPreferencesAction
{
    public function __construct(private readonly NotificationPreferences $preferences) {}

    /**
     * @param  array<string, array<string, bool>>  $matrix  [категория => [канал => bool]]
     */
    public function handle(User $user, array $matrix): void
    {
        $now = now();
        $rows = [];

        foreach (NotificationCategory::cases() as $category) {
            foreach (NotificationChannel::available() as $channel) {
                $rows[] = [
                    // upsert идёт мимо Eloquent, поэтому UUID генерируем сами.
                    'id' => (string) Str::uuid7(),
                    'user_id' => $user->getKey(),
                    'category' => $category->value,
                    'channel' => $channel->value,
                    'enabled' => (bool) ($matrix[$category->value][$channel->value] ?? true),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        NotificationPreference::upsert(
            $rows,
            ['user_id', 'category', 'channel'],
            ['enabled', 'updated_at'],
        );

        $user->unsetRelation('notificationPreferences');
        $this->preferences->forget($user);
    }
}
