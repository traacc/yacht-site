<?php

declare(strict_types=1);

namespace App\Filament\RichEditor\CustomBlocks;

use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\RichEditor\RichEditorTool;

/**
 * Базовый класс блоков RichEditor этого проекта.
 *
 * Отличие от стандартного RichContentCustomBlock — обязательная кнопка тулбара:
 * блоки вставляются прямыми кнопками, а не через выпадающую панель «Блоки»
 * (см. App\Support\RichContent::toolbarButtons()).
 */
abstract class ContentBlock extends RichContentCustomBlock
{
    abstract public static function editorTool(): RichEditorTool;
}
