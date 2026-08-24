<?php

declare(strict_types=1);

namespace App\Actions\Help;

use App\Services\SettingsService;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final readonly class SaveHelpPageSettingsAction
{
    public function __construct(private SettingsService $settings) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): void
    {
        $documents = collect((array) ($data['site_guide_documents'] ?? []))
            ->filter(fn (mixed $document): bool => is_array($document) && ! empty($document['title']))
            ->map(function (array $document): array {
                $file = $document['file'] ?? [];
                if (is_array($file)) {
                    $file = $file === [] ? null : reset($file);
                }
                if ($file instanceof TemporaryUploadedFile) {
                    $file = null;
                }

                return [
                    'title' => (string) ($document['title'] ?? ''),
                    'desc' => (string) ($document['desc'] ?? ''),
                    'file' => is_string($file) ? $file : null,
                    'show_on_pages' => array_values(array_filter(
                        (array) ($document['show_on_pages'] ?? []),
                        'is_string',
                    )),
                ];
            })
            ->filter(fn (array $document): bool => $document['file'] !== null)
            ->values()
            ->all();

        $this->settings->set('help.before_note', $data['before_note'] ?? '', 'help');
        $this->settings->set('help.site_guide', $data['site_guide'] ?? '', 'help');
        $this->settings->set('help.site_guide_documents', $documents, 'help');
        $this->settings->forgetGroup('help');
    }
}
