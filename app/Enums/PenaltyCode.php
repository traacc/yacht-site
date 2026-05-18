<?php

namespace App\Enums;

enum PenaltyCode: string
{
    case DNF = 'DNF'; // Did Not Finish
    case DNS = 'DNS'; // Did Not Start
    case DSQ = 'DSQ'; // Disqualified
    case OCS = 'OCS'; // On Course Side (фальстарт)
    case RET = 'RET'; // Retired
    case BFD = 'BFD'; // Black Flag Disqualification
    case UFD = 'UFD'; // Under Flag Disqualification

    public function label(): string
    {
        return match($this) {
            self::DNF => 'Не финишировал',
            self::DNS => 'Не стартовал',
            self::DSQ => 'Дисквалификация',
            self::OCS => 'Фальстарт (OCS)',
            self::RET => 'Отказ от гонки',
            self::BFD => 'Дисквалификация чёрным флагом',
            self::UFD => 'Дисквалификация (U-флаг)',
        };
    }

    /**
     * Очки по правилам ISAF:
     * штрафное очко = число финишировавших яхт + 1
     * Метод: передаём общее число участников.
     */
    public function computePoints(int $entrantsCount): float
    {
        return (float) ($entrantsCount + 1);
    }
}
