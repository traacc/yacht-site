<?php

declare(strict_types=1);

namespace App\Actions\WorldNews;

use App\Contracts\AiNewsProvider;
use App\Enums\AiNewsCandidateStatus;
use App\Models\AiNewsCandidate;
use App\Services\Ai\Data\AiNewsArticle;
use App\Services\WorldNews\ArticleImageExtractor;
use App\Services\WorldNews\Data\DiscoveryResult;
use App\Services\WorldNews\UrlCanonicalizer;
use App\Services\WorldNews\WorldNewsSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class DiscoverSailingNewsAction
{
    private const ALLOWED_HTML = '<p><h2><h3><ul><ol><li><strong><em><blockquote>';

    public function __construct(
        private readonly AiNewsProvider $provider,
        private readonly WorldNewsSettings $settings,
        private readonly UrlCanonicalizer $urls,
        private readonly ArticleImageExtractor $images,
        private readonly PublishAiNewsCandidateAction $publish,
    ) {}

    public function handle(): DiscoveryResult
    {
        $request = $this->settings->request();
        $batch = $this->provider->discover($request);

        $created = 0;
        $updated = 0;
        $rejected = 0;
        $published = 0;
        $skipped = 0;

        foreach (array_slice($batch->articles, 0, $request->maxItems) as $article) {
            $prepared = $this->prepare($article);

            if ($prepared === null) {
                $skipped++;

                continue;
            }

            $existing = AiNewsCandidate::withTrashed()
                ->where('source_hash', $prepared['source_hash'])
                ->first();

            if ($existing !== null && ($existing->trashed() || ! $existing->canBePublished())) {
                $skipped++;

                continue;
            }

            $isRejected = $prepared['relevance_score'] < $this->settings->minRelevance()
                || $this->isOutsideLookback($prepared['source_published_at']);

            $attributes = [
                ...$prepared,
                'status' => $isRejected
                    ? AiNewsCandidateStatus::Rejected
                    : AiNewsCandidateStatus::Pending,
                'ai_response_id' => $batch->responseId,
                'ai_model' => $batch->model,
                'discovered_at' => now(),
            ];

            try {
                if ($existing === null) {
                    $candidate = AiNewsCandidate::query()->create($attributes);
                    $created++;
                } else {
                    $existing->update($attributes);
                    $candidate = $existing->refresh();
                    $updated++;
                }
            } catch (QueryException $exception) {
                if ($this->isDuplicateKey($exception)) {
                    $skipped++;

                    continue;
                }

                throw $exception;
            }

            if ($isRejected) {
                $rejected++;

                continue;
            }

            if ($this->settings->autoPublish()) {
                $this->publish->handle($candidate);
                $published++;
            }
        }

        return new DiscoveryResult(
            received: count($batch->articles),
            created: $created,
            updated: $updated,
            rejected: $rejected,
            published: $published,
            skipped: $skipped,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function prepare(AiNewsArticle $article): ?array
    {
        $sourceUrl = $this->urls->canonicalize($article->sourceUrl);
        $sourceHash = $this->urls->fingerprint($article->sourceUrl);
        $title = Str::limit(trim(strip_tags($article->title)), 255, '');
        $sourceName = Str::limit(trim(strip_tags($article->sourceName)), 255, '');
        $content = Str::sanitizeHtml(strip_tags($article->content, self::ALLOWED_HTML));
        $summary = Str::limit(Str::squish(strip_tags($article->summary)), 1000, '');
        $reason = Str::limit(Str::squish(strip_tags($article->selectionReason)), 2000, '');

        if ($sourceUrl === null
            || $sourceHash === null
            || $title === ''
            || $sourceName === ''
            || trim(strip_tags($content)) === ''
            || strlen($content) > 60000) {
            return null;
        }

        return [
            'title' => $title,
            'summary' => $summary !== '' ? $summary : null,
            'content' => $content,
            'source_name' => $sourceName,
            'source_url' => $sourceUrl,
            'source_hash' => $sourceHash,
            // Ссылку достаём сразу, чтобы модератор видел превью; сам файл
            // скачивается только при публикации, см. PublishAiNewsCandidateAction.
            'image_url' => $this->images->extract($sourceUrl),
            'source_published_at' => $this->parseDate($article->sourcePublishedAt),
            'relevance_score' => $article->relevanceScore,
            'selection_reason' => $reason !== '' ? $reason : null,
        ];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function isOutsideLookback(?Carbon $publishedAt): bool
    {
        if ($publishedAt === null) {
            return false;
        }

        return $publishedAt->isBefore(now()->subDays($this->settings->lookbackDays())->startOfDay())
            || $publishedAt->isAfter(now()->addDay());
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['1062', '23000'], true);
    }
}
