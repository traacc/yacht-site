<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\User;

/**
 * Применяет галочки, отмеченные при регистрации.
 *
 * На форме регистрации выбираются только категории — отмеченная категория
 * включается по всем каналам, снятая выключается целиком. Дальше пользователь
 * настраивает каналы точечно в личном кабинете.
 */
final class ApplyRegistrationPreferencesAction
{
    public function __construct(private readonly SaveNotificationPreferencesAction $save) {}

    /**
     * @param  list<string>  $enabledCategories  значения NotificationCategory
     */
    public function handle(User $user, array $enabledCategories): void
    {
        $matrix = [];

        foreach (NotificationCategory::cases() as $category) {
            $enabled = in_array($category->value, $enabledCategories, true);

            foreach (NotificationChannel::available() as $channel) {
                $matrix[$category->value][$channel->value] = $enabled;
            }
        }

        $this->save->handle($user, $matrix);
    }
}
