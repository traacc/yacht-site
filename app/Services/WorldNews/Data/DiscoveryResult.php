<?php

declare(strict_types=1);

namespace App\Services\WorldNews\Data;

final readonly class DiscoveryResult
{
    public function __construct(
        public int $received,
        public int $created,
        public int $updated,
        public int $rejected,
        public int $published,
        public int $skipped,
    ) {}

    public function summary(): string
    {
        return "Получено: {$this->received}; новых: {$this->created}; обновлено: {$this->updated}; отклонено фильтром: {$this->rejected}; опубликовано: {$this->published}; пропущено: {$this->skipped}.";
    }
}
