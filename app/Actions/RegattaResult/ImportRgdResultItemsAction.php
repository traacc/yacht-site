<?php

declare(strict_types=1);

namespace App\Actions\RegattaResult;

use App\Models\RegattaResult;
use App\Services\Rgd\RgdParser;

/**
 * Импортирует результаты одного зачётного класса из .rgd в выбранный результат
 * регаты: итоговую таблицу (RegattaResultItem) и пооночные результаты (RaceResult).
 *
 * Тонкая обёртка: разбирает .rgd через RgdParser в каноническую структуру
 * (races + crews) и делегирует запись в БД общему движку ApplyRegattaResultsAction.
 * Тот же движок использует JSON-API внешней программы (RegattaResultsController),
 * поэтому логика привязки/идемпотентности живёт в одном месте.
 *
 * Результат регаты (RegattaResult) выбирается/создаётся заранее и передаётся готовым;
 * регата берётся из него.
 *
 * @see ApplyRegattaResultsAction — общий движок записи (привязка по яхте, идемпотентность).
 * @see ImportRegattaResultItemsAction — аналогичный импорт из CSV (только итоги, по имени).
 */
class ImportRgdResultItemsAction
{
    public function __construct(
        private readonly RgdParser $parser,
        private readonly ApplyRegattaResultsAction $apply,
    ) {}

    /**
     * @return array{imported: int, skipped: int, errors: string[], created_yachts: int, created_teams: int}
     */
    public function execute(
        RegattaResult $result,
        string $rgdContent,
        string $class,
        bool $createMissing = false,
        bool $replace = false,
    ): array {
        $data = $this->parser->parse($rgdContent, $class);

        return $this->apply->execute($result, $data, $createMissing, $replace);
    }
}
