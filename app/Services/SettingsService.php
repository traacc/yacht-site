<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingsService
{
    private const CACHE_TTL = 3600;

    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting:{$key}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = Setting::find($key);
            return $setting?->value ?? $default;
        });
    }

    public function set(string $key, mixed $value, string $group = 'general'): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group],
        );

        Cache::forget("setting:{$key}");
    }

    public function setMany(array $data, string $group = 'general'): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value, $group);
        }
    }

    public function getGroup(string $group): array
    {
        return Cache::remember("settings_group:{$group}", self::CACHE_TTL, function () use ($group) {
            return Setting::where('group', $group)
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    public function forgetGroup(string $group): void
    {
        $keys = Setting::where('group', $group)->pluck('key');
        foreach ($keys as $key) {
            Cache::forget("setting:{$key}");
        }
        Cache::forget("settings_group:{$group}");
    }

    // ──────────────────────────────────────────────
    // Уведомления администраторам
    // ──────────────────────────────────────────────

    /**
     * E-mail'ы администраторов для системных уведомлений
     * (заявки на регату, регистрация команд / яхт / пользователей).
     *
     * @return list<string>
     */
    public function adminNotificationEmails(): array
    {
        // Обратная совместимость: ранее список хранился в home.regatta_entry_emails.
        $raw = $this->get('home.admin_notification_emails', null)
            ?? $this->get('home.regatta_entry_emails', []);

        return collect((array) $raw)
            ->flatten()
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();
    }

    // ──────────────────────────────────────────────
    // Галерея главной страницы
    // ──────────────────────────────────────────────

    /**
     * Возвращает список публичных URL фотографий для галереи главной страницы.
     *
     * Логика:
     *  - Читает пул фото, количество, флаг рандома и порядок сортировки из settings.
     *  - Если random_gallery = true  → перемешивает пул и берёт первые N.
     *  - Если random_gallery = false → сортирует по выбранному правилу и берёт первые N.
     *
     * @return Collection<int, string>  Коллекция публичных URL изображений.
     */
    public function getGalleryPhotos(): Collection
    {
        $raw    = $this->get('home.gallery_photos', []);
        $count  = (int) $this->get('home.gallery_count', 10);
        $random = (bool) $this->get('home.gallery_random', false);
        $sort   = $this->get('home.gallery_sort', 'manual') ?? 'manual';

        // Нормализуем: значение может быть массивом строк, ассоциативным массивом
        // или вложенным массивом — приводим к плоскому списку строк.
        $paths = collect((array) $raw)
            ->flatten()
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values()
            ->all();

        if (empty($paths)) {
            return collect();
        }

        $collection = collect($paths);

        if ($random) {
            // Случайный режим: перемешиваем весь пул, берём первые $count
            $collection = $collection->shuffle();
        } else {
            // Фиксированный режим: применяем выбранную сортировку
            $collection = match ($sort) {
                // «Сначала новые» — разворачиваем массив (последние загруженные идут первыми)
                'newest' => $collection->reverse()->values(),
                // «Сначала старые» — оставляем исходный порядок
                'oldest' => $collection->values(),
                // «Ручной» — порядок как задан в настройках
                default  => $collection->values(),
            };
        }

        // Берём нужное количество и конвертируем пути в публичные URL
        return $collection
            ->take($count)
            ->map(fn (string $path) => Storage::disk('public')->url($path))
            ->values();
    }

    // ──────────────────────────────────────────────
    // Hero-фон главной страницы
    // ──────────────────────────────────────────────

    /**
     * Возвращает данные о фоновом медиа для hero-блока главной страницы.
     *
     * @return array{url: string, type: 'video'|'image'}|null
     *         Публичный URL и тип медиа, либо null если файл не загружен.
     */
    public function getHeroMedia(): ?array
    {
        $path = collect((array) $this->get('home.hero_media', []))
            ->flatten()
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->first();

        if (! $path) {
            return null;
        }

        $videoExtensions = ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v'];
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return [
            'url'  => Storage::disk('public')->url($path),
            'type' => in_array($extension, $videoExtensions, true) ? 'video' : 'image',
        ];
    }
}
