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
    // Документы контентных страниц
    // ──────────────────────────────────────────────

    /**
     * Репитер документов из настроек → готовые для вывода ссылки.
     *
     * Страницы «Устав», «Регламент», «Решения», «Ремонт и модернизация» хранят
     * документы одинаково: массив {title, desc, file}, где file — путь на диске
     * public. Публичному сайту нужен URL, поэтому нормализация вынесена сюда —
     * иначе она копируется в каждое замыкание роута.
     *
     * @return list<array{title: string, desc: string, file_url: string|null, original_name: string|null}>
     */
    public function documentLinks(string $key): array
    {
        return collect((array) $this->get($key, []))
            ->filter(fn ($document) => is_array($document) && ! empty($document['title']))
            ->map(function (array $document): array {
                $filePath = $document['file'] ?? null;
                $hasFile = is_string($filePath) && $filePath !== '';

                return [
                    'title' => (string) ($document['title'] ?? ''),
                    'desc' => (string) ($document['desc'] ?? ''),
                    'file_url' => $hasFile ? Storage::disk('public')->url($filePath) : null,
                    'original_name' => $hasFile ? basename($filePath) : null,
                ];
            })
            ->values()
            ->all();
    }

    // ──────────────────────────────────────────────
    // Уведомления администраторам
    // ──────────────────────────────────────────────

    /**
     * Адрес отдела заказов: заявки на аренду, ремонт и услуги.
     *
     * По ТЗ 3-го этапа все запросы коммерческих разделов уходят на один ящик,
     * поэтому адрес настраиваемый, а не захардкоженный в Action-классах.
     */
    public function orderEmail(): string
    {
        $email = trim((string) $this->get('site.order_email', ''));

        return $email !== '' ? $email : 'order@carter-pro.ru';
    }

    /**
     * Слать письма центра уведомлений только на подтверждённые адреса.
     *
     * Касается только канала E-mail центра уведомлений (@see \App\Services\Notifications\NotificationPreferences).
     * Письма подтверждения адреса, восстановления пароля и системные письма
     * по заявкам отправляются всегда, независимо от этой настройки.
     */
    public function notifyVerifiedEmailsOnly(): bool
    {
        return (bool) $this->get('home.notify_verified_emails_only', false);
    }

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
     * @return Collection<int, string> Коллекция публичных URL изображений.
     */
    public function getGalleryPhotos(): Collection
    {
        $raw = $this->get('home.gallery_photos', []);
        $count = (int) $this->get('home.gallery_count', 10);
        $random = (bool) $this->get('home.gallery_random', false);
        $sort = $this->get('home.gallery_sort', 'manual') ?? 'manual';

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
                default => $collection->values(),
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
     * Режимы:
     *  - один файл  → одиночный фон ('video' или 'image', как раньше);
     *  - несколько изображений → слайд-шоу ('slideshow' со списком URL);
     *  - видео среди набора игнорируются для слайд-шоу (слайды только из изображений).
     *
     * @return array{type: 'video'|'image', url: string}|array{type: 'slideshow', slides: list<string>}|null
     *                                                                                                       Данные медиа, либо null если ничего не загружено.
     */
    public function getHeroMedia(): ?array
    {
        $paths = collect((array) $this->get('home.hero_media', []))
            ->flatten()
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values();

        if ($paths->isEmpty()) {
            return null;
        }

        $videoExtensions = ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v'];
        $isVideo = fn (string $path): bool => in_array(
            strtolower((string) pathinfo($path, PATHINFO_EXTENSION)),
            $videoExtensions,
            true,
        );
        $url = fn (string $path): string => Storage::disk('public')->url($path);

        // Одиночный файл — прежнее поведение (image или video).
        if ($paths->count() === 1) {
            $path = (string) $paths->first();

            return [
                'url' => $url($path),
                'type' => $isVideo($path) ? 'video' : 'image',
            ];
        }

        // Несколько файлов — слайд-шоу только из изображений.
        $slides = $paths->reject($isVideo)->map($url)->values();

        // Изображений не осталось (загружены только видео) — берём первое как одиночный фон.
        if ($slides->isEmpty()) {
            return [
                'url' => $url((string) $paths->first()),
                'type' => 'video',
            ];
        }

        // Единственное изображение — обычный одиночный фон.
        if ($slides->count() === 1) {
            return [
                'url' => (string) $slides->first(),
                'type' => 'image',
            ];
        }

        return [
            'type' => 'slideshow',
            'slides' => $slides->all(),
        ];
    }

    /**
     * Настройки видимой области (viewport) hero-изображения на сайте.
     *
     * Управляют не кадрированием файла, а тем, какая часть медиа видна в блоке.
     * Задаются crop-прямоугольником в долях изображения [0..1]:
     *  - crop_x/crop_y — левый верхний угол видимой области;
     *  - crop_w/crop_h — ширина/высота видимой области;
     *  - height — высота блока при Full HD (px, ≤768); пропорция блока = пропорции прямоугольника.
     *
     * @return array{crop_x: float, crop_y: float, crop_w: float, crop_h: float, height: int}
     */
    public function getHeroViewport(): array
    {
        return [
            'crop_x' => (float) $this->get('home.hero_crop_x', 0.0),
            'crop_y' => (float) $this->get('home.hero_crop_y', 0.0),
            'crop_w' => (float) $this->get('home.hero_crop_w', 1.0),
            'crop_h' => (float) $this->get('home.hero_crop_h', 1.0),
            'height' => (int) $this->get('home.hero_height', 768),
        ];
    }
}
