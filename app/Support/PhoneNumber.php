<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Российские номера телефонов: приведение к единому виду.
 *
 * В users.phone номер хранится в маске Filament «+7 (999) 999-99-99»,
 * а провайдеру и в БД кодов подтверждения нужен «7XXXXXXXXXX» без разделителей.
 */
final class PhoneNumber
{
    /**
     * Только цифры в формате 7XXXXXXXXXX; null — если номер непригоден.
     * Ведущая 8 и +7 приводятся к 7, номер из 10 цифр дополняется семёркой.
     */
    public static function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '7'.$digits;
        }

        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7'.substr($digits, 1);
        }

        return strlen($digits) === 11 && $digits[0] === '7' ? $digits : null;
    }

    /**
     * Международный формат «+7XXXXXXXXXX» — в таком виде номер принимает
     * API сервиса «Звонок»; null — если номер непригоден.
     */
    public static function international(?string $raw): ?string
    {
        $digits = self::normalize($raw);

        return $digits === null ? null : '+'.$digits;
    }

    /** Номер в маске «+7 (999) 999-99-99»; null — если номер непригоден. */
    public static function format(?string $raw): ?string
    {
        $digits = self::normalize($raw);

        if ($digits === null) {
            return null;
        }

        return sprintf(
            '+7 (%s) %s-%s-%s',
            substr($digits, 1, 3),
            substr($digits, 4, 3),
            substr($digits, 7, 2),
            substr($digits, 9, 2),
        );
    }

    /**
     * Номер для логов: «+7 (999) ***-**-21».
     * Полный номер в логи не пишем — это персональные данные.
     */
    public static function mask(?string $raw): string
    {
        $digits = self::normalize($raw);

        if ($digits === null) {
            return '—';
        }

        return sprintf('+7 (%s) ***-**-%s', substr($digits, 1, 3), substr($digits, 9, 2));
    }
}
