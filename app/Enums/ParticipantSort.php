<?php

declare(strict_types=1);

namespace App\Enums;

use Collator;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Carbon;

/**
 * Порядок строк в списках участников (сезон — личный рейтинг, регата).
 *
 * Строка списка — массив с ключами `name`, `team`, `birth_date` (?Carbon)
 * и, для сезона, `rank` (?int). Пустые значения уходят в конец, при равенстве
 * строки идут по ФИО.
 */
enum ParticipantSort: string implements HasLabel
{
    case Name = 'name';
    case Team = 'team';
    case Rank = 'rank';
    case BirthDate = 'birth_date';

    public function getLabel(): string
    {
        return match ($this) {
            self::Name => 'По фамилии',
            self::Team => 'По команде',
            self::Rank => 'По рейтингу',
            self::BirthDate => 'По дате рождения',
        };
    }

    /**
     * Варианты для Select. В списке регаты рейтинга нет — `withRank: false`.
     *
     * @return array<string, string>
     */
    public static function options(bool $withRank = true): array
    {
        return collect(self::cases())
            ->reject(fn (self $sort) => ! $withRank && $sort === self::Rank)
            ->mapWithKeys(fn (self $sort) => [$sort->value => $sort->getLabel()])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function apply(array $rows): array
    {
        $collator = new Collator('ru_RU');
        $byName = fn (array $a, array $b): int => $collator->compare($a['name'], $b['name']);

        usort($rows, fn (array $a, array $b): int => match ($this) {
            self::Name => 0,
            self::Team => self::nullsLast($a['team'], $b['team'], fn ($x, $y) => $collator->compare($x, $y)),
            self::Rank => self::nullsLast($a['rank'] ?? null, $b['rank'] ?? null, fn ($x, $y) => $x <=> $y),
            // От старших к младшим.
            self::BirthDate => self::nullsLast($a['birth_date'], $b['birth_date'], fn (Carbon $x, Carbon $y) => $x <=> $y),
        } ?: $byName($a, $b));

        return $rows;
    }

    private static function nullsLast(mixed $a, mixed $b, callable $compare): int
    {
        return match (true) {
            $a === null && $b === null => 0,
            $a === null => 1,
            $b === null => -1,
            default => $compare($a, $b),
        };
    }
}
