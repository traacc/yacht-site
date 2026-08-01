<?php

declare(strict_types=1);

namespace App\Filament\RichEditor\CustomBlocks;

use App\Support\VideoEmbed;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * Блок «Видео» для RichEditor — плеер YouTube / VK / Rutube / Vimeo внутри текста.
 *
 * Ссылка превращается в адрес плеера общим для сайта разбором (App\Support\VideoEmbed),
 * тем же, что у видео галерей, туров и кейсов ремонта.
 *
 * Внимание: <iframe> проходит через Str::sanitizeHtml() и вырезался бы целиком —
 * элемент разрешён точечно в AppServiceProvider::register().
 */
class VideoBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'video';
    }

    public static function getLabel(): string
    {
        return 'Видео';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalDescription('Поддерживаются YouTube (в т.ч. Shorts), VK Видео, Rutube и Vimeo.')
            ->schema([
                TextInput::make('url')
                    ->label('Ссылка на видео')
                    ->placeholder('https://vkvideo.ru/video-12345_67890')
                    ->url()
                    ->required()
                    ->rules([
                        static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                            if (is_string($value) && $value !== '' && ! VideoEmbed::supports($value)) {
                                $fail('Ссылка не распознана. Нужен адрес видео на YouTube, VK Видео, Rutube или Vimeo.');
                            }
                        },
                    ]),
                TextInput::make('caption')
                    ->label('Подпись')
                    ->placeholder('Необязательно, например: Финишная гонка второго этапа')
                    ->maxLength(255),
                Select::make('ratio')
                    ->label('Формат кадра')
                    ->options([
                        '16:9' => 'Горизонтальное (16:9)',
                        '4:3' => 'Классическое (4:3)',
                        '9:16' => 'Вертикальное (9:16, Shorts)',
                    ])
                    ->default('16:9')
                    ->selectablePlaceholder(false)
                    ->required(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function getPreviewLabel(array $config): string
    {
        $caption = trim((string) ($config['caption'] ?? ''));

        return $caption !== '' ? 'Видео — '.$caption : 'Видео';
    }

    /**
     * Превью внутри редактора: карточка со ссылкой, без плеера —
     * тянуть сторонний iframe в форму незачем. Стили только инлайновые.
     *
     * @param  array<string, mixed>  $config
     */
    public static function toPreviewHtml(array $config): string
    {
        $url = trim((string) ($config['url'] ?? ''));

        if ($url === '') {
            return '<div style="padding:0.75rem;color:#6b7280;font-size:0.875rem;">Видео: ссылка не указана</div>';
        }

        $caption = trim((string) ($config['caption'] ?? ''));

        return '<div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem;background:#f3f4f6;border-radius:0.5rem;">'
            .'<span style="flex:none;display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;border-radius:9999px;background:#2d92ce;color:#fff;">&#9654;</span>'
            .'<span style="min-width:0;">'
            .($caption !== '' ? '<span style="display:block;font-weight:600;">'.e($caption).'</span>' : '')
            .'<span style="display:block;font-size:0.875rem;color:#6b7280;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'.e($url).'</span>'
            .'</span>'
            .'</div>';
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    public static function toHtml(array $config, array $data): ?string
    {
        $url = trim((string) ($config['url'] ?? ''));

        if ($url === '' || ! VideoEmbed::supports($url)) {
            return null;
        }

        $caption = trim((string) ($config['caption'] ?? ''));

        return view('rich-content.video', [
            'embedUrl' => VideoEmbed::url($url),
            'caption' => $caption !== '' ? $caption : null,
            'ratio' => (string) ($config['ratio'] ?? '16:9'),
        ])->render();
    }
}
