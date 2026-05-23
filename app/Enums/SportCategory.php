<?php

namespace App\Enums;

enum SportCategory: string
{
    case Third  = '3';
    case Second = '2';
    case First  = '1';
    case Kms    = 'kms';
    case Ms     = 'ms';
    case Msmk   = 'msmk';
    case Zms    = 'zms';

    public function getLabel(): string
    {
        return match ($this) {
            self::Third  => 'Третий',
            self::Second => 'Второй',
            self::First  => 'Первый',
            self::Kms    => 'КМС',
            self::Ms     => 'МС',
            self::Msmk   => 'МСМК',
            self::Zms    => 'ЗМС',
        };
    }
}
