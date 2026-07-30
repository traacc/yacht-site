<?php

namespace App\Models;

use App\Support\VideoEmbed;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoLink extends Model
{
    use HasUuids;

    protected $table = 'video_links';

    protected $fillable = [
        'gallery_id',
        'url',
        'title',
        'sort_order',
    ];

    protected $appends = ['embed_url'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Преобразует URL видео в embed-URL для iframe.
     *
     * @see VideoEmbed — разбор ссылок общий с кейсами ремонта
     *                                раздела «Carter 30».
     */
    public function getEmbedUrlAttribute(): string
    {
        return VideoEmbed::url((string) $this->url);
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
