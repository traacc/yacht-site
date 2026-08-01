<?php

declare(strict_types=1);

namespace App\Filament\RichEditor\CustomBlocks;

use App\Services\ImageConverter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Блок «Галерея» для RichEditor — сетка фотографий прямо внутри текста.
 *
 * Вёрстка блока проходит через Str::sanitizeHtml() (см. RichContentRenderer::toHtml),
 * поэтому разметка строго статическая: ни x-data, ни data-*, ни <template> не переживут
 * санитайзер. Лайтбокс навешивается на классы .rich-gallery__link уровнем выше —
 * в компоненте <x-rich-content>.
 */
class GalleryBlock extends RichContentCustomBlock
{
    /** Каталог на диске public, куда складываются изображения блока. */
    public const DIRECTORY = 'news/inline-gallery';

    /** @var array<string> */
    public const ACCEPTED_FILE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
        'image/heic',
        'image/heif',
    ];

    public static function getId(): string
    {
        return 'gallery';
    }

    public static function getLabel(): string
    {
        return 'Галерея';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalDescription('Сетка фотографий внутри текста. На странице новости снимки открываются в лайтбоксе.')
            ->schema([
                TextInput::make('title')
                    ->label('Заголовок галереи')
                    ->placeholder('Необязательно, например: Фоторепортаж с гонки')
                    ->maxLength(255),
                Select::make('columns')
                    ->label('Колонок в сетке')
                    ->options([
                        '2' => '2 колонки',
                        '3' => '3 колонки',
                        '4' => '4 колонки',
                    ])
                    ->default('3')
                    ->selectablePlaceholder(false)
                    ->required(),
                FileUpload::make('images')
                    ->label('Изображения')
                    ->helperText('Порядок снимков можно менять перетаскиванием.')
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->image()
                    ->imageEditor()
                    ->acceptedFileTypes(self::ACCEPTED_FILE_TYPES)
                    ->disk('public')
                    ->directory(self::DIRECTORY)
                    ->visibility('public')
                    // HEIC не показывается браузерами: концерн NormalizesHeicImageColumns
                    // сюда не достаёт (путь живёт в JSON блока, а не в колонке), поэтому
                    // перекодируем сразу при загрузке.
                    ->saveUploadedFileUsing(static fn (TemporaryUploadedFile $file): ?string => app(ImageConverter::class)
                        ->normalizeHeicToWebp($file->store(self::DIRECTORY, 'public')))
                    ->required(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function getPreviewLabel(array $config): string
    {
        $count = count(static::getImagePaths($config));

        return $count > 0
            ? 'Галерея — '.$count.' фото'
            : 'Галерея (пусто)';
    }

    /**
     * Превью внутри редактора. Стили только инлайновые — утилит Tailwind
     * публичного сайта в админке нет.
     *
     * @param  array<string, mixed>  $config
     */
    public static function toPreviewHtml(array $config): string
    {
        $paths = static::getImagePaths($config);

        if ($paths === []) {
            return '<div style="padding:0.75rem;color:#6b7280;font-size:0.875rem;">Галерея: изображения не выбраны</div>';
        }

        $thumbnails = collect($paths)
            ->take(6)
            ->map(fn (string $path): string => '<img src="'.e(static::getImageUrl($path)).'" alt="" style="width:5rem;height:5rem;object-fit:cover;border-radius:0.25rem;">')
            ->implode('');

        $rest = count($paths) > 6
            ? '<span style="font-size:0.875rem;color:#6b7280;align-self:center;">+'.(count($paths) - 6).'</span>'
            : '';

        $title = filled($config['title'] ?? null)
            ? '<div style="font-weight:600;margin-bottom:0.5rem;">'.e($config['title']).'</div>'
            : '';

        return $title.'<div style="display:flex;flex-wrap:wrap;gap:0.5rem;">'.$thumbnails.$rest.'</div>';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    public static function toHtml(array $config, array $data): ?string
    {
        $paths = static::getImagePaths($config);

        if ($paths === []) {
            return null;
        }

        return view('rich-content.gallery', [
            'title' => filled($config['title'] ?? null) ? (string) $config['title'] : null,
            'columns' => (int) ($config['columns'] ?? 3),
            'images' => array_map(
                fn (string $path): array => [
                    'url' => static::getImageUrl($path),
                    'name' => pathinfo($path, PATHINFO_FILENAME),
                ],
                $paths,
            ),
        ])->render();
    }

    /**
     * Пути изображений блока. FileUpload::multiple() хранит состояние
     * ассоциативным массивом (uuid => путь), в JSON он приезжает объектом.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    protected static function getImagePaths(array $config): array
    {
        return collect((array) ($config['images'] ?? []))
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values()
            ->all();
    }

    protected static function getImageUrl(string $path): string
    {
        return Storage::disk('public')->url($path);
    }
}
