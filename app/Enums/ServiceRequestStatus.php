<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Состояние заявки на услугу.
 *
 * Отдельный статус, а не булев `processed_at`: подарочные сертификаты (ТЗ 3-го
 * этапа, п. 7) требуют состояний «оплачен» и «услуга оказана» — они добавятся
 * сюда кейсом, без ENUM-миграции (колонка в БД — `string(32)`).
 */
enum ServiceRequestStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новая',
            self::InProgress => 'В работе',
            self::Done => 'Выполнена',
            self::Rejected => 'Отклонена',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::InProgress => 'info',
            self::Done => 'success',
            self::Rejected => 'danger',
        };
    }

    /** Закрыта ли заявка: для таких проставлены processed_at / processed_by. */
    public function isClosed(): bool
    {
        return $this === self::Done || $this === self::Rejected;
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
