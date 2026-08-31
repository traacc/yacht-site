<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiNewsCandidateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Найденный AI-движком материал до переноса в публичную ленту News.
 */
class AiNewsCandidate extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'news_id',
        'title',
        'summary',
        'content',
        'source_name',
        'source_url',
        'source_hash',
        'image_url',
        'source_published_at',
        'status',
        'relevance_score',
        'selection_reason',
        'ai_response_id',
        'ai_model',
        'discovered_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiNewsCandidateStatus::class,
            'relevance_score' => 'integer',
            'source_published_at' => 'datetime',
            'discovered_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AiNewsCandidateStatus::Pending->value);
    }

    public function canBePublished(): bool
    {
        return $this->status === AiNewsCandidateStatus::Pending && $this->news_id === null;
    }

    public function sourceHost(): ?string
    {
        $host = parse_url($this->source_url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./i', '', $host) : null;
    }
}
