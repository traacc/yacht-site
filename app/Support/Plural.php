<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Русские числительные: «1 яхта», «2 яхты», «5 яхт».
 *
 * Своя реализация, а не trans_choice(): на строке, которой нет в файлах
 * переводов, Laravel применяет двухформенное правило и выдаёт «0 яхты».
 */
final class Plural
{
    /**
     * @param  string  $one  форма для 1 («яхта»)
     * @param  string  $few  форма для 2–4 («яхты»)
     * @param  string  $many  форма для 0, 5–20 («яхт»)
     */
    public static function form(int $number, string $one, string $few, string $many): string
    {
        $number = abs($number);

        $mod100 = $number % 100;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return $many;
        }

        return match ($number % 10) {
            1 => $one,
            2, 3, 4 => $few,
            default => $many,
        };
    }

    /** Число вместе с подходящей формой слова: «3 яхты». */
    public static function with(int $number, string $one, string $few, string $many): string
    {
        return $number.' '.self::form($number, $one, $few, $many);
    }
}
