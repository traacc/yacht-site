<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Yachts\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\Yacht\UpdateYachtRequiredDocumentsAction;
use App\Filament\User\Resources\Yachts\YachtResource;
use App\Models\Yacht;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageYachts extends ManageRecords
{
    protected static string $resource = YachtResource::class;

    /**
     * Возвращает динамический список обязательных документов из настроек.
     *
     * @return array<int, array{doc_type: string, title: string}>
     */
    public static function getRequiredDocuments(): array
    {
        return app(UpdateYachtRequiredDocumentsAction::class)->getRequiredList();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Зарегистрировать яхту')
                ->modalHeading('Зарегистрировать яхту')
                ->using(function (array $data, string $model): Yacht {
                    $docs = $data['required_documents'] ?? [];
                    $selectedYachtId = $data['selected_yacht_id'] ?? null;
                    unset($data['required_documents'], $data['yacht_search'], $data['selected_yacht_id']);

                    if ($selectedYachtId) {
                        /** @var Yacht $record */
                        $record = Yacht::findOrFail($selectedYachtId);
                        $record->update([
                            ...$data,
                            'user_id'         => auth()->id(),
                            'approval_status' => 'approved',
                        ]);
                    } else {
                        $data['user_id']         = auth()->id();
                        $data['approval_status'] = 'approved';

                        /** @var Yacht $record */
                        $record = $model::create($data);
                    }

                    app(SyncDocumentFilesAction::class)->execute($record, $docs);

                    return $record;
                })
                ->after(function (): void {
                    $this->mountAction('showInfoModal');
                })
                ->createAnother(false)
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Готово!')
                        ->body('Поздравляем с успешной регистрацией яхты'),
                ),
        ];
    }

    public function getShowInfoModalAction(): Action
    {
        return Action::make('showInfoModal')
            ->modalHeading('Успех!')
            ->modalDescription('Новый элемент был успешно добавлен в систему.')
            ->modalSubmitActionLabel('Понятно')
            ->modalCancelAction(false)
            ->action(fn () => null);
    }
}
