<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Тип соревнования.
 *
 * Тип определяет способ подачи заявки и набор полей регаты, но не отменяет
 * рейтинги: регата любого типа идёт в зачёт по своему `level_coefficient`,
 * а «вне зачёта» выражается коэффициентом 0 (@see App\Services\RatingCalculator).
 *
 * Цвет типа — основной ориентир в календарях и списке регат: фон плашки
 * в графическом календаре и бейдж в текстовом. Статус (предстоящая,
 * состоявшаяся) ушёл в фильтры, поэтому палитра статусов здесь не участвует.
 */
enum RegattaType: string implements HasColor, HasLabel
{
    /** Клубная: заявляются экипажи, возможен добор людей со стороны. */
    case Club = 'club';

    /** Регулярная: ассоциация выставляет лодки и продаёт места на них. */
    case Regular = 'regular';

    /** Выездная: проводится вне московского региона. */
    case Travel = 'travel';

    public function getLabel(): string
    {
        return match ($this) {
            self::Club => 'Клубная',
            self::Regular => 'Регулярная',
            self::Travel => 'Выездная',
        };
    }

    /** Множественное число — для фильтров и легенд («Клубные», «Выездные»). */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Club => 'Клубные',
            self::Regular => 'Регулярные',
            self::Travel => 'Выездные',
        };
    }

    /** Полное название для заголовков и писем. */
    public function title(): string
    {
        return match ($this) {
            self::Club => 'Клубная регата',
            self::Regular => 'Регулярная регата',
            self::Travel => 'Выездная регата',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Club => 'info',
            self::Regular => 'warning',
            self::Travel => 'success',
        };
    }

    /**
     * Цвет типа на публичном сайте.
     *
     * Классы записаны литералами: Tailwind сканирует исходники и не увидит
     * класс, собранный из переменной (@see AGENTS.md, «npm run build»).
     */
    public function backgroundClass(): string
    {
        return match ($this) {
            self::Club => 'bg-[#2D92CE]',
            self::Regular => 'bg-[#C2A36B]',
            self::Travel => 'bg-[#157949]',
        };
    }

    /** Возможна ли индивидуальная заявка (одним человеком, без экипажа). */
    public function allowsIndividualEntry(): bool
    {
        return $this !== self::Club;
    }

    /**
     * Предельный размер экипажа, заданный самим типом.
     *
     * Регулярные — шесть мест на лодке ассоциации. Выездные зависят от флота
     * конкретной регаты, поэтому лимит хранится в `regattas.crew_size_limit`
     * (@see App\Models\Regatta::maxCrewSize()).
     */
    public function crewSizeLimit(): ?int
    {
        return $this === self::Regular ? 6 : null;
    }

    /** Проходят ли заявки этого типа модерацию администратором. */
    public function requiresModeration(): bool
    {
        return $this !== self::Club;
    }

    /** @return array<string, string> value => label, для Select и фильтров. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->getLabel()])
            ->all();
    }
}
