<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Размеры членского взноса в Ассоциацию.
 *
 * По ТЗ 3-го этапа взнос берётся с яхты и устанавливается на текущий год,
 * поэтому в настройках хранится не одно число, а ставка на каждый год
 * (`membership.fee_rates`): прошлые остаются историей, будущую можно завести
 * заранее. Задаёт ставки администратор в разделе «Правила вступления»
 * (@see \App\Filament\Pages\MembershipRulesPageSettings), там же они и публикуются.
 *
 * Группа настроек — `membership`.
 */
class MembershipFees
{
    public const SETTING_GROUP = 'membership';

    public const RATES_KEY = 'membership.fee_rates';

    public const INTRO_KEY = 'membership.fee_intro';

    public const UNIT_KEY = 'membership.fee_unit';

    public const PUBLISH_KEY = 'membership.fee_published';

    public const DEFAULT_UNIT = 'за одну яхту в год';

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Все заведённые ставки, свежие сверху.
     *
     * @return list<array{year: int, amount: float, note: string, formatted: string}>
     */
    public function rates(): array
    {
        return collect((array) $this->settings->get(self::RATES_KEY, []))
            ->filter(fn ($rate): bool => is_array($rate) && filled($rate['year'] ?? null) && filled($rate['amount'] ?? null))
            ->map(fn (array $rate): array => [
                'year' => (int) $rate['year'],
                'amount' => (float) $rate['amount'],
                'note' => trim((string) ($rate['note'] ?? '')),
                'formatted' => self::format((float) $rate['amount']),
            ])
            // Год — ключ ставки: если админ завёл его дважды, остаётся последняя запись.
            ->keyBy('year')
            ->sortKeysDesc()
            ->values()
            ->all();
    }

    /**
     * Действующая ставка: на текущий год, иначе — последняя установленная из прошлых.
     *
     * Год возвращается вместе с суммой и всегда выводится рядом с ней, поэтому
     * забытая администратором ставка не выдаёт себя за актуальную.
     *
     * @return array{year: int, amount: float, note: string, formatted: string}|null
     */
    public function current(): ?array
    {
        $year = (int) now()->year;

        return collect($this->rates())
            ->first(fn (array $rate): bool => $rate['year'] <= $year);
    }

    public function currentAmount(): ?float
    {
        return $this->current()['amount'] ?? null;
    }

    /**
     * Ставки на будущие годы (заведённые заранее), ближайшие сверху.
     *
     * @return list<array{year: int, amount: float, note: string, formatted: string}>
     */
    public function upcoming(): array
    {
        $year = (int) now()->year;

        return collect($this->rates())
            ->filter(fn (array $rate): bool => $rate['year'] > $year)
            ->sortBy('year')
            ->values()
            ->all();
    }

    /** Подпись к сумме: с чего и за какой период берётся взнос. */
    public function unit(): string
    {
        $unit = trim((string) $this->settings->get(self::UNIT_KEY, ''));

        return $unit !== '' ? $unit : self::DEFAULT_UNIT;
    }

    /** Текст о порядке уплаты взноса (RichEditor, рендерится через <x-rich-content>). */
    public function intro(): string
    {
        return (string) $this->settings->get(self::INTRO_KEY, '');
    }

    public function isPublished(): bool
    {
        return (bool) $this->settings->get(self::PUBLISH_KEY, true);
    }

    /**
     * Данные блока «Членский взнос» на странице «Правила вступления».
     *
     * Прошлые ставки на сайт не идут — они остаются историей в админке.
     *
     * @return array{
     *     current: array{year: int, amount: float, note: string, formatted: string}|null,
     *     upcoming: list<array{year: int, amount: float, note: string, formatted: string}>,
     *     unit: string,
     *     intro: string,
     * }|null
     */
    public function publication(): ?array
    {
        if (! $this->isPublished()) {
            return null;
        }

        $current = $this->current();
        $upcoming = $this->upcoming();
        $intro = $this->intro();

        if ($current === null && $upcoming === [] && blank(strip_tags($intro))) {
            return null;
        }

        return [
            'current' => $current,
            'upcoming' => $upcoming,
            'unit' => $this->unit(),
            'intro' => $intro,
        ];
    }

    public static function format(float $amount): string
    {
        return number_format($amount, fmod($amount, 1.0) === 0.0 ? 0 : 2, ',', ' ').' ₽';
    }
}
