<?php

declare(strict_types=1);

namespace App\Services\Ai\Data;

final readonly class AiNewsRequest
{
    public function __construct(
        public string $systemPrompt,
        public string $searchPrompt,
        public int $maxItems,
    ) {}
}
