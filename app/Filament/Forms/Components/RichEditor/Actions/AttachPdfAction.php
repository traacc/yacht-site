<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components\RichEditor\Actions;

use App\Filament\Forms\Components\PdfRichEditor;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class AttachPdfAction
{
    public const NAME = 'attachPdf';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('Загрузить PDF')
            ->modalHeading('Добавить ссылку на PDF')
            ->modalDescription('Файл будет загружен, а ссылка вставлена в текущую позицию текста.')
            ->modalWidth(Width::Large)
            ->fillForm(fn (array $arguments): array => [
                'title' => trim((string) ($arguments['selectedText'] ?? '')),
            ])
            ->schema(fn (PdfRichEditor $component): array => [
                FileUpload::make('file')
                    ->label('PDF-файл')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize($component->getPdfAttachmentsMaxSize())
                    ->storeFiles(false)
                    ->required(),
                TextInput::make('title')
                    ->label('Текст ссылки')
                    ->helperText('Если оставить пустым, будет использовано имя файла.')
                    ->maxLength(255),
            ])
            ->action(function (array $arguments, array $data, PdfRichEditor $component): void {
                $file = $data['file'] ?? null;

                if (! $file instanceof TemporaryUploadedFile) {
                    return;
                }

                $url = $component->storePdfAttachment($file);
                if ($url === null) {
                    return;
                }

                $title = trim((string) ($data['title'] ?? ''));
                if ($title === '') {
                    $title = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                }

                $component->runCommands(
                    [
                        EditorCommand::make('insertContent', arguments: [[
                            'type' => 'text',
                            'text' => $title,
                            'marks' => [[
                                'type' => 'link',
                                'attrs' => [
                                    'href' => $url,
                                    'target' => '_blank',
                                    'rel' => 'noopener noreferrer',
                                ],
                            ]],
                        ]]),
                    ],
                    editorSelection: $arguments['editorSelection'] ?? null,
                );
            });
    }
}
