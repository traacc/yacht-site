<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Help;
use App\Models\HelpCategory;
use App\Support\ResponsiveMedia;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Справочник специалистов технической помощи.
 *
 * Один и тот же справочник выводится в двух местах: вкладкой «Для владельцев
 * яхт» на странице «Помощь» и подразделом «Техническая помощь» раздела
 * «Carter 30» (ТЗ 3-го этапа, п. 5). Источник контента общий, поэтому сборка
 * данных живёт здесь, а не в замыканиях роутов.
 */
class HelpDirectory
{
    /** @var Collection<int, HelpCategory>|null */
    private ?Collection $categories = null;

    /**
     * Категории с карточками специалистов, готовые к передаче в Alpine.
     *
     * Порядок категорий задаётся drag&drop в админке (HelpCategoryResource);
     * title — вторичный ключ для категорий с одинаковым sort_order.
     *
     * @return array<string, array{title: string, description: string|null, items: list<array<string, mixed>>}>
     */
    public function categories(): array
    {
        return $this->loadCategories()
            ->mapWithKeys(fn (HelpCategory $category) => [
                $category->slug => [
                    'title' => $category->title,
                    'description' => $category->description,
                    'items' => $category->helps->map($this->mapHelp(...))->values()->toArray(),
                ],
            ])
            ->toArray();
    }

    /** Slug первой категории — активная вкладка по умолчанию. */
    public function defaultCategory(): string
    {
        return $this->loadCategories()->first()?->slug ?? '';
    }

    /** @return Collection<int, HelpCategory> */
    private function loadCategories(): Collection
    {
        // Запрос один на экземпляр сервиса: страница «Помощь» просит и список
        // категорий, и slug первой из них.
        return $this->categories ??= HelpCategory::with([
            'helps' => fn ($q) => $q->active()->orderBy('title')->with('media'),
        ])
            ->whereHas('helps', fn ($q) => $q->active())
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    /** @return array<string, mixed> */
    private function mapHelp(Help $help): array
    {
        return [
            'id' => $help->id,
            'title' => $help->title,
            'desc' => $help->desc,
            'includes' => collect($help->includes ?? [])
                ->map(fn ($inc) => is_array($inc) ? ($inc['item'] ?? '') : (string) $inc)
                ->filter()
                ->values()
                ->all(),
            'name' => $help->specialist_name,
            'phone' => $help->specialist_phone,
            'email' => $help->specialist_email,
            'sphere' => $help->specialist_sphere,
            'city' => $help->specialist_city,
            'site' => $help->specialist_site,
            'contactType' => $help->contact_type,
            'gallery' => $help->getMedia('gallery')->map(function ($media) {
                $urls = ResponsiveMedia::urls($media);

                return [
                    'url' => $urls['src'],
                    'webp' => $urls['webp'] ?? null,
                    'avif' => $urls['avif'] ?? null,
                ];
            })->values()->toArray(),
            'documents' => $help->getMedia('documents')->map(fn (Media $media) => [
                'url' => $media->getUrl(),
                'name' => $media->name ?: $media->file_name,
                'ext' => strtoupper(pathinfo($media->file_name, PATHINFO_EXTENSION)),
                'size' => $media->human_readable_size,
            ])->values()->toArray(),
        ];
    }
}
