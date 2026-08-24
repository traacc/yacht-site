<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Filament\Forms\Components\RichEditor\Actions\AttachPdfAction;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PdfRichEditor extends RichEditor
{
    public const ATTACH_PDF_TOOL = 'attachPdf';

    protected string|Closure|null $pdfAttachmentsDirectory = null;

    protected string|Closure|null $pdfAttachmentsDiskName = null;

    protected string|Closure|null $pdfAttachmentsVisibility = null;

    protected int|Closure|null $pdfAttachmentsMaxSize = 10240;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tools([
            RichEditorTool::make(self::ATTACH_PDF_TOOL)
                ->label('Загрузить PDF')
                ->action(
                    action: AttachPdfAction::NAME,
                    arguments: "{ selectedText: editorSelection?.head !== undefined ? \$getEditor().state.doc.textBetween(editorSelection.anchor, editorSelection.head, ' ') : '' }",
                )
                ->activeStyling(false)
                ->icon('icon-pdf'),
        ]);
    }

    /**
     * @return array<string|array<string>>
     */
    public function getDefaultToolbarButtons(): array
    {
        $groups = parent::getDefaultToolbarButtons();

        foreach ($groups as &$group) {
            if (! is_array($group)) {
                continue;
            }

            $position = array_search('attachFiles', $group, true);
            if ($position === false) {
                $position = array_search('table', $group, true);
            }
            if ($position === false) {
                continue;
            }

            array_splice($group, $position + 1, 0, [self::ATTACH_PDF_TOOL]);

            break;
        }
        unset($group);

        return $groups;
    }

    /**
     * @return array<Action>
     */
    public function getDefaultActions(): array
    {
        return [
            ...parent::getDefaultActions(),
            AttachPdfAction::make(),
        ];
    }

    public function pdfAttachmentsDirectory(string|Closure|null $directory): static
    {
        $this->pdfAttachmentsDirectory = $directory;

        return $this;
    }

    public function pdfAttachmentsDisk(string|Closure|null $diskName): static
    {
        $this->pdfAttachmentsDiskName = $diskName;

        return $this;
    }

    public function pdfAttachmentsVisibility(string|Closure|null $visibility): static
    {
        $this->pdfAttachmentsVisibility = $visibility;

        return $this;
    }

    public function pdfAttachmentsMaxSize(int|Closure|null $size): static
    {
        $this->pdfAttachmentsMaxSize = $size;

        return $this;
    }

    public function getPdfAttachmentsDirectory(): ?string
    {
        return $this->evaluate($this->pdfAttachmentsDirectory) ?? $this->getFileAttachmentsDirectory();
    }

    public function getPdfAttachmentsDiskName(): string
    {
        return $this->evaluate($this->pdfAttachmentsDiskName) ?? $this->getFileAttachmentsDiskName();
    }

    public function getPdfAttachmentsVisibility(): string
    {
        return $this->evaluate($this->pdfAttachmentsVisibility) ?? $this->getFileAttachmentsVisibility();
    }

    public function getPdfAttachmentsMaxSize(): ?int
    {
        return $this->evaluate($this->pdfAttachmentsMaxSize);
    }

    public function storePdfAttachment(TemporaryUploadedFile $file): ?string
    {
        $diskName = $this->getPdfAttachmentsDiskName();
        $directory = $this->getPdfAttachmentsDirectory() ?? '';

        $path = $this->getPdfAttachmentsVisibility() === 'public'
            ? $file->storePublicly($directory, $diskName)
            : $file->store($directory, $diskName);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk($diskName)->url($path);
    }
}
