<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Состояние заявки на услугу.
 *
 * Отдельный статус, а не булев `processed_at`: подарочные сертификаты (ТЗ 3-го
 * этапа, п. 7) требуют состояний «оплачен» и «услуга оказана». Колонка в БД —
 * `string(32)`, поэтому новый кейс не требует ENUM-миграции.
 *
 * Два закрывающих «успешных» статуса не дублируют друг друга: «Услуга оказана»
 * закрывает заявку, прошедшую через оплату (заказ сертификата), «Выполнена» —
 * заявку без оплаты (подбор флота, обучение). Оплату пока отмечает менеджер
 * вручную: эквайринг ещё не подключён (ТЗ п. 4.1).
 */
enum ServiceRequestStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Paid = 'paid';
    case Fulfilled = 'fulfilled';
    case Done = 'done';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новая',
            self::InProgress => 'В работе',
            self::Paid => 'Оплачена',
            self::Fulfilled => 'Услуга оказана',
            self::Done => 'Выполнена',
            self::Rejected => 'Отклонена',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::InProgress => 'info',
            self::Paid => 'primary',
            self::Fulfilled, self::Done => 'success',
            self::Rejected => 'danger',
        };
    }

    /** Закрыта ли заявка: для таких проставлены processed_at / processed_by. */
    public function isClosed(): bool
    {
        return match ($this) {
            self::Fulfilled, self::Done, self::Rejected => true,
            default => false,
        };
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
