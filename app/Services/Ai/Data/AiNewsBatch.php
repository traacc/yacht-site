<?php

declare(strict_types=1);

namespace App\Services\Ai\Data;

final readonly class AiNewsBatch
{
    /**
     * @param  list<AiNewsArticle>  $articles
     */
    public function __construct(
        public string $responseId,
        public string $model,
        public array $articles,
    ) {}
}
