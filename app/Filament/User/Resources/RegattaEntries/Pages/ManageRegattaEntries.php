<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\RegattaEntries\Pages;

use App\Actions\RegattaEntry\UpdateRegattaEntryRequiredDocumentsAction;
use App\Filament\User\Resources\RegattaEntries\RegattaEntryResource;
use App\Models\RegattaEntry;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\UniqueConstraintViolationException;

class ManageRegattaEntries extends ManageRecords
{
    protected static string $resource = RegattaEntryResource::class;

    /**
     * Возвращает динамический список обязательных документов из настроек.
     *
     * @return array<int, array{doc_type: string, title: string}>
     */
    public static function getRequiredDocuments(): array
    {
        return app(UpdateRegattaEntryRequiredDocumentsAction::class)->getRequiredList();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(fn (array $data): array => array_merge($data, [
                    'status' => 'approved',
                ]))
                ->using(function (array $data, string $model): \Illuminate\Database\Eloquent\Model {
                    try {
                        return $model::create($data);
                    } catch (UniqueConstraintViolationException) {
                        Notification::make()
                            ->title('Заявка уже существует')
                            ->body('Эта команда уже подала заявку на выбранную регату.')
                            ->danger()
                            ->send();

                        $this->halt();
                    }
                })->createAnother(false)->successNotification(
                Notification::make()
                    ->success()
                    ->title('Готово!')
                    ->body('Ваша заявка успешно подана, ожидайте подтверждения')),
        ];
    }

    /** Создаёт недостающие обязательные документы для заявки */
    public static function ensureRequiredDocuments(RegattaEntry $entry): void
    {
        foreach (static::getRequiredDocuments() as $doc) {
            $entry->documents()->firstOrCreate(
                ['doc_type' => $doc['doc_type']],
                [
                    'title' => $doc['title'],
                    'url'   => '',
                ],
            );
        }
    }
}
