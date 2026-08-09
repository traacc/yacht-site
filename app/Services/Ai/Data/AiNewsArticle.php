<?php

declare(strict_types=1);

namespace App\Services\Ai\Data;

use InvalidArgumentException;

final readonly class AiNewsArticle
{
    public function __construct(
        public string $title,
        public string $summary,
        public string $content,
        public string $sourceName,
        public string $sourceUrl,
        public ?string $sourcePublishedAt,
        public int $relevanceScore,
        public string $selectionReason,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $requiredStrings = [
            'title',
            'summary',
            'article_html',
            'source_name',
            'source_url',
            'source_published_at',
            'selection_reason',
        ];

        foreach ($requiredStrings as $key) {
            if (! array_key_exists($key, $data) || ! is_string($data[$key])) {
                throw new InvalidArgumentException("Некорректное поле AI-ответа: {$key}");
            }
        }

        if (! isset($data['relevance_score']) || ! is_int($data['relevance_score'])) {
            throw new InvalidArgumentException('Некорректное поле AI-ответа: relevance_score');
        }

        $publishedAt = trim($data['source_published_at']);

        return new self(
            title: trim($data['title']),
            summary: trim($data['summary']),
            content: trim($data['article_html']),
            sourceName: trim($data['source_name']),
            sourceUrl: trim($data['source_url']),
            sourcePublishedAt: $publishedAt !== '' ? $publishedAt : null,
            relevanceScore: max(0, min(100, $data['relevance_score'])),
            selectionReason: trim($data['selection_reason']),
        );
    }
}
