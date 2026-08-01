<?php

declare(strict_types=1);

namespace App\Support;

use App\Filament\RichEditor\CustomBlocks\GalleryBlock;
use App\Filament\RichEditor\CustomBlocks\VideoBlock;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\RichEditor\RichContentRenderer;

/**
 * Рендер контента, созданного в Filament RichEditor.
 *
 * Хранимый HTML нельзя выводить через {!! !!} напрямую: кастомные блоки лежат в нём
 * заготовкой (<div data-type="customBlock" …>) и превращаются в вёрстку только здесь.
 * Заодно RichContentRenderer::toHtml() прогоняет результат через Str::sanitizeHtml().
 *
 * Диск вложений задаётся явно: config('filament.default_filesystem_disk') = local,
 * и без этого рендерер не найдёт картинки, вставленные в текст, и обнулит их src.
 */
class RichContent
{
    /**
     * Блоки, доступные в редакторе. Список должен совпадать с RichEditor::customBlocks(),
     * иначе блок не отрисуется на публичной странице.
     *
     * @var array<class-string<RichContentCustomBlock>>
     */
    public const BLOCKS = [
        GalleryBlock::class,
        VideoBlock::class,
    ];

    public static function render(?string $content): string
    {
        if (blank($content)) {
            return '';
        }

        return RichContentRenderer::make($content)
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsVisibility('public')
            ->customBlocks(self::BLOCKS)
            ->toHtml();
    }
}
