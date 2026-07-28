<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Перевод хранимого времени в местный часовой пояс для показа пользователю.
 *
 * Приложение работает и хранит время в UTC (config('app.timezone')) — менять
 * это нельзя, иначе все накопленные записи сдвинутся. Поэтому пояс
 * подставляется только при выводе.
 */
final class DisplayTime
{
    public const DATE_TIME = 'd.m.Y H:i';

    public const TIME = 'H:i';

    public static function timezone(): string
    {
        return (string) config('app.display_timezone', 'UTC');
    }

    /** Тот же момент времени в местном поясе. */
    public static function local(?DateTimeInterface $value): ?CarbonInterface
    {
        return $value === null
            ? null
            : Carbon::instance($value)->timezone(self::timezone());
    }

    public static function format(
        ?DateTimeInterface $value,
        string $format = self::DATE_TIME,
        string $placeholder = '—',
    ): string {
        return self::local($value)?->format($format) ?? $placeholder;
    }
}
