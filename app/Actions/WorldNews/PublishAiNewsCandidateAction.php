<?php

declare(strict_types=1);

namespace App\Actions\WorldNews;

use App\Enums\AiNewsCandidateStatus;
use App\Models\AiNewsCandidate;
use App\Models\News;
use App\Services\WorldNews\CoverImageDownloader;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class PublishAiNewsCandidateAction
{
    public function __construct(private readonly CoverImageDownloader $covers) {}

    public function handle(AiNewsCandidate $candidate, ?string $authorId = null): News
    {
        // Обложку качаем до транзакции: HTTP-запрос под lockForUpdate держал бы
        // строку заблокированной всё время скачивания.
        $coverPath = $candidate->canBePublished()
            ? $this->covers->store($candidate->image_url)
            : null;

        try {
            return $this->createNews($candidate, $authorId, $coverPath);
        } catch (Throwable $exception) {
            if ($coverPath !== null) {
                Storage::disk('public')->delete($coverPath);
            }

            throw $exception;
        }
    }

    private function createNews(AiNewsCandidate $candidate, ?string $authorId, ?string $coverPath): News
    {
        return DB::transaction(function () use ($candidate, $authorId, $coverPath): News {
            /** @var AiNewsCandidate|null $locked */
            $locked = AiNewsCandidate::query()
                ->lockForUpdate()
                ->find($candidate->getKey());

            if ($locked === null) {
                throw new DomainException('Кандидат новости не найден.');
            }

            if ($locked->status === AiNewsCandidateStatus::Published && $locked->news_id !== null) {
                $news = News::find($locked->news_id);

                if ($news !== null) {
                    return $news;
                }
            }

            if (! $locked->canBePublished()) {
                throw new DomainException('Опубликовать можно только материал на модерации.');
            }

            $news = News::query()->create([
                'author_id' => $authorId,
                'type' => 'external',
                'title' => $locked->title,
                'content' => $locked->content,
                'cover_image_url' => $coverPath,
                'external_url' => $locked->source_url,
                'source_name' => $locked->source_name,
                'source_published_at' => $locked->source_published_at,
                'published_at' => now(),
            ]);

            $locked->update([
                'status' => AiNewsCandidateStatus::Published,
                'news_id' => $news->id,
                'published_at' => now(),
            ]);

            return $news;
        });
    }
}
