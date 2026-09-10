<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SportCategory: string implements HasLabel
{
    case No = 'no';
    case Third = '3';
    case Second = '2';
    case First = '1';
    case Kms = 'kms';
    case Ms = 'ms';
    case Msmk = 'msmk';
    case Zms = 'zms';

    /**
     * Подпись для публичного вывода: отсутствие разряда (NULL в БД) неотличимо
     * от явного «без разряда», поэтому показываем «б/р» в обоих случаях.
     */
    public static function labelOrNone(?self $category): string
    {
        return ($category ?? self::No)->getLabel();
    }

    /**
     * Короткая подпись разряда для судейской программы: формат колонки «Рзр»
     * судейского .rgd («3 р», «КМС»), он же уходит во внешний JSON-API
     * (RgdParticipantsExporter, ParticipantResource). Отсутствие разряда
     * (NULL в БД) неотличимо от явного «без разряда» — в обоих случаях «б/р».
     */
    public static function shortLabelOrNone(?self $category): string
    {
        return ($category ?? self::No)->getShortLabel();
    }

    public function getShortLabel(): string
    {
        return match ($this) {
            self::No => 'б/р',
            self::Third => '3 р',
            self::Second => '2 р',
            self::First => '1 р',
            self::Kms => 'КМС',
            self::Ms => 'МС',
            self::Msmk => 'МСМК',
            self::Zms => 'ЗМС',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::No => 'б/р',
            self::Third => 'Третий',
            self::Second => 'Второй',
            self::First => 'Первый',
            self::Kms => 'КМС',
            self::Ms => 'МС',
            self::Msmk => 'МСМК',
            self::Zms => 'ЗМС',
        };
    }
}
