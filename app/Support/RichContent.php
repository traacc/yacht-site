<?php

declare(strict_types=1);

namespace App\Support;

use App\Filament\RichEditor\CustomBlocks\ContentBlock;
use App\Filament\RichEditor\CustomBlocks\GalleryBlock;
use App\Filament\RichEditor\CustomBlocks\VideoBlock;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\RichEditor\RichEditorTool;

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
     * @var array<class-string<ContentBlock>>
     */
    public const BLOCKS = [
        GalleryBlock::class,
        VideoBlock::class,
    ];

    /**
     * Инструменты тулбара — по кнопке на каждый блок.
     *
     * @return array<RichEditorTool>
     */
    public static function editorTools(): array
    {
        return array_map(
            static fn (string $block): RichEditorTool => $block::editorTool(),
            self::BLOCKS,
        );
    }

    /**
     * Кнопки тулбара: набор Filament по умолчанию, где выпадающая панель «Блоки»
     * заменена прямыми кнопками блоков.
     *
     * Перечислять приходится целиком: toolbarButtons() задаёт список, а не дополняет его.
     *
     * @return array<array<string>>
     */
    public static function toolbarButtons(): array
    {
        return [
            ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
            ['h2', 'h3'],
            ['alignStart', 'alignCenter', 'alignEnd'],
            ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
            [
                'table',
                'attachFiles',
                ...array_map(static fn (string $block): string => $block::getId(), self::BLOCKS),
            ],
            ['undo', 'redo'],
        ];
    }

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

    /**
     * HTML из RichEditor → обычный текст с переводами строк (для анонсов, meta, мессенджеров).
     *
     * Голый strip_tags() склеивает абзацы: «<p>Конец.</p><p>Начало</p>» → «Конец.Начало»,
     * поэтому границы блоков и <br> сначала превращаются в переводы строк.
     * Для однострочного вывода (meta description, короткий анонс) оборачивай в Str::squish().
     */
    public static function toPlainText(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $text = preg_replace('~<br\s*/?>|</(p|div|li|h[1-6]|blockquote|tr)>~i', "\n", $html);
        $text = preg_replace('~</t[dh]>~i', ' ', $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[^\S\n]+/u', ' ', $text);
        $text = preg_replace('/ ?\n ?/', "\n", $text);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
