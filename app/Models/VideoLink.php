<?php

namespace App\Models;

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
     * Поддерживаемые платформы:
     *   - YouTube (watch → embed)
     *   - YouTube Shorts (shorts → embed)
     *   - Vimeo (vimeo.com/... → player.vimeo.com/video/...)
     *   - Rutube (rutube.ru/video/... → rutube.ru/play/embed/...)
     *   - VK Видео (vk.com/video... → vk.com/video_ext.php?...)
     *
     * Для всех остальных URL возвращается исходное значение.
     */
    public function getEmbedUrlAttribute(): string
    {
        $url = $this->url;

        // YouTube: https://www.youtube.com/watch?v=VIDEO_ID
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        // YouTube Shorts: https://www.youtube.com/shorts/VIDEO_ID
        if (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        // Vimeo: https://vimeo.com/VIDEO_ID
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        // Rutube: https://rutube.ru/video/VIDEO_ID/...
        if (preg_match('/rutube\.ru\/video\/([a-zA-Z0-9]+)/', $url, $m)) {
            return 'https://rutube.ru/play/embed/' . $m[1];
        }

        // VK Видео: https://vk.com/video-OWNER_ID_VIDEO_ID
        if (preg_match('/vk\.com\/video(-?\d+_\d+)/', $url, $m)) {
            return 'https://vk.com/video_ext.php?' . $m[1];
        }

        // Неизвестный источник — возвращаем как есть
        return $url;
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
