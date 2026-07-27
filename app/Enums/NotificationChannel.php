<?php

declare(strict_types=1);

namespace App\Enums;

use App\Notifications\Channels\MaxChannel;
use App\Notifications\Channels\TelegramChannel;
use Filament\Support\Contracts\HasLabel;

/**
 * Канал доставки уведомления.
 *
 * MAX зарезервирован под будущий мессенджер: чтобы его включить, достаточно
 * написать App\Notifications\Channels\MaxChannel и убрать его из исключений
 * isAvailable() — хранение настроек и UI строятся по available() и не меняются.
 */
enum NotificationChannel: string implements HasLabel
{
    case Email = 'email';
    case Telegram = 'telegram';
    case Database = 'database';
    case Max = 'max';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Telegram => 'Telegram',
            self::Database => 'В личном кабинете',
            self::Max => 'MAX',
        };
    }

    /** Имя канала для Notification::via(): встроенный драйвер Laravel или класс-драйвер. */
    public function driver(): string
    {
        return match ($this) {
            self::Email => 'mail',
            self::Database => 'database',
            self::Telegram => TelegramChannel::class,
            self::Max => MaxChannel::class,
        };
    }

    /** Канал реализован и показывается пользователю. */
    public function isAvailable(): bool
    {
        return $this !== self::Max;
    }

    /** Каналу нужна привязка внешнего аккаунта, без неё доставка невозможна. */
    public function requiresBinding(): bool
    {
        return in_array($this, [self::Telegram, self::Max], true);
    }

    /**
     * Каналы, доступные пользователю в настройках.
     *
     * @return list<self>
     */
    public static function available(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $channel): bool => $channel->isAvailable(),
        ));
    }
}
