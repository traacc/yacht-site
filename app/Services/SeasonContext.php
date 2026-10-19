<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Season;
use Illuminate\Support\Collection;

/**
 * Единое правило «какой сезон показывать».
 *
 * Сайт и админка фильтруют регаты, заявки, результаты, серии и рейтинги по
 * одному сезону, чтобы данные разных лет не смешивались. Здесь решается, какой
 * сезон текущий, какие сезоны вообще предлагать к выбору и как выбранный год
 * превращается в запись Season.
 *
 * Регистрируется как scoped: мемоизация живёт в пределах одного запроса.
 */
final class SeasonContext
{
    private bool $currentResolved = false;

    private ?Season $current = null;

    /** @var Collection<int, Season>|null */
    private ?Collection $available = null;

    /**
     * Текущий сезон.
     *
     * Сезон, в который попадает сегодняшняя дата, — если в нём уже есть регаты.
     * В межсезонье, пока регаты нового года не заведены, остаётся последний
     * сезон с регатами: открывать пустую страницу нет смысла.
     */
    public function current(): ?Season
    {
        if ($this->currentResolved) {
            return $this->current;
        }

        $this->currentResolved = true;

        $today = Season::current();

        if ($today?->regattas()->exists()) {
            return $this->current = $today;
        }

        return $this->current = Season::query()
            ->where('year', '<=', now()->year)
            ->whereHas('regattas')
            ->orderByDesc('year')
            ->first() ?? $today;
    }

    /**
     * Сезоны для переключателя: с данными плюс текущий, новые сверху.
     *
     * @return Collection<int, Season>
     */
    public function available(): Collection
    {
        if ($this->available !== null) {
            return $this->available;
        }

        $seasons = Season::query()->inUse()->get();
        $current = $this->current();

        if ($current && ! $seasons->contains('id', $current->id)) {
            $seasons->push($current);
        }

        return $this->available = $seasons->sortByDesc('year')->values();
    }

    /**
     * Сезон по году из запроса; неизвестный или пустой год — текущий сезон.
     */
    public function resolve(int|string|null $year): ?Season
    {
        if (filled($year)) {
            $season = $this->available()->firstWhere('year', (int) $year);

            if ($season) {
                return $season;
            }
        }

        return $this->current();
    }

    /**
     * Годы для публичного переключателя: ключ === значение (custom-select
     * отдаёт в wire:model ключ).
     *
     * @return array<int, string>
     */
    public function yearOptions(): array
    {
        return $this->available()
            ->mapWithKeys(fn (Season $season) => [$season->year => (string) $season->year])
            ->all();
    }

    /**
     * Сезоны для селектов админки: id => год.
     *
     * @return array<string, int>
     */
    public function idOptions(): array
    {
        return $this->available()->pluck('year', 'id')->all();
    }
}
