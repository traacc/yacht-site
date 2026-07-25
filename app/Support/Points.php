<?php

namespace App\Support;

class Points
{
    /**
     * Форматирует очки для показа: минимум один знак после запятой, сотые —
     * только если они значащие (12 → «12,0», 12.25 → «12,25»).
     */
    public static function format(mixed $value): string
    {
        $formatted = number_format((float) $value, 2, ',', ' ');

        return str_ends_with($formatted, '0') ? substr($formatted, 0, -1) : $formatted;
    }
}
