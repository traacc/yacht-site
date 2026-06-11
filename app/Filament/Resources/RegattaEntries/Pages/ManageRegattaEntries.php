<?php

declare(strict_types=1);

namespace App\Filament\Resources\RegattaEntries\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\RegattaEntry\UpdateRegattaEntryRequiredDocumentsAction;
use App\Filament\Resources\RegattaEntries\RegattaEntryResource;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Livewire\Attributes\On;

class ManageRegattaEntries extends ManageRecords
{
    protected static string $resource = RegattaEntryResource::class;

    /**
     * Перезаписывает существующую заявку данными из формы и удаляет
     * редактируемую запись. Вызывается кнопкой «Перезаписать» в уведомлении
     * о дубликате.
     */
    #[On('overwriteRegattaEntry')]
    public function overwriteRegattaEntry(string $recordId): void
    {
        $payload = session()->pull("regatta_entry_overwrite:{$recordId}");

        $record = $payload ? RegattaEntry::find($recordId) : null;
        $conflict = $payload ? RegattaEntry::find($payload['conflict_id']) : null;

        if (! $payload || ! $record || ! $conflict) {
            Notification::make()
                ->title('Не удалось перезаписать заявку')
                ->body('Данные формы устарели. Откройте заявку и попробуйте снова.')
                ->danger()
                ->send();

            $this->dispatch('notificationsSent');

            return;
        }

        $conflict->update($payload['data']);
        app(SyncDocumentFilesAction::class)->execute($conflict, $payload['docs']);
        RegattaEntryResource::syncCrew($conflict, $payload['crew']);

        $record->delete();

        // Закрываем открытое модальное окно редактирования удалённой записи.
        $this->unmountAction();

        Notification::make()
            ->title('Заявка перезаписана')
            ->success()
            ->send();

        // Слушатель вызван событием от кнопки уведомления — авто-синхронизация
        // не срабатывает, поэтому явно просим компонент забрать уведомление.
        $this->dispatch('notificationsSent');
    }

    /**
     * Перезаписывает существующую заявку данными из формы создания.
     * Новая запись не создаётся — обновляется уже существующий дубликат.
     * Вызывается кнопкой «Перезаписать» в уведомлении о дубликате.
     */
    #[On('overwriteRegattaEntryOnCreate')]
    public function overwriteRegattaEntryOnCreate(string $conflictId): void
    {
        $payload = session()->pull("regatta_entry_overwrite:create:{$conflictId}");
        $conflict = $payload ? RegattaEntry::find($conflictId) : null;

        if (! $payload || ! $conflict) {
            Notification::make()
                ->title('Не удалось перезаписать заявку')
                ->body('Данные формы устарели. Откройте форму и попробуйте снова.')
                ->danger()
                ->send();

            $this->dispatch('notificationsSent');

            return;
        }

        $conflict->update($payload['data']);
        app(SyncDocumentFilesAction::class)->execute($conflict, $payload['docs']);
        RegattaEntryResource::syncCrew($conflict, $payload['crew']);

        // Закрываем открытое модальное окно создания.
        $this->unmountAction();

        Notification::make()
            ->title('Заявка перезаписана')
            ->success()
            ->send();

        // Слушатель вызван событием от кнопки уведомления — авто-синхронизация
        // не срабатывает, поэтому явно просим компонент забрать уведомление.
        $this->dispatch('notificationsSent');
    }

    /**
     * Возвращает список документов для заявки с флагом обязательности.
     *
     * Если передан $regattaId и у регаты настроены собственные документы — возвращает их.
     * Иначе — глобальные настройки.
     *
     * @return array<int, array{doc_type: string, title: string, is_required: bool}>
     */
    public static function getRequiredDocuments(?string $regattaId = null): array
    {
        if ($regattaId !== null) {
            $regatta = Regatta::find($regattaId);

            if ($regatta && ! empty($regatta->entry_required_documents)) {
                return $regatta->getEntryDocuments();
            }
        }

        return app(UpdateRegattaEntryRequiredDocumentsAction::class)->getRequiredList();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('documentSettings')
                ->label('Документы')
                ->icon('heroicon-o-document-text')
                ->color('white')
                ->url(fn () => \App\Filament\Resources\RegattaEntryDocumentTypeResource::getUrl()),
            CreateAction::make()
            ->modalHeading('Новая заявка на регату')
                ->createAnother(false)
                ->using(function (array $data, string $model): RegattaEntry {
                    $requiredDocs = $data['required_documents'] ?? [];
                    $crew = $data['crew'] ?? [];
                    unset($data['required_documents'], $data['crew']);

                    // Проверка дубликата до записи в БД
                    /** @var RegattaEntry $model */
                    $conflict = $model::where('regatta_id', $data['regatta_id'])
                        ->where('team_id', $data['team_id'])
                        ->first();

                    if ($conflict) {
                        // Сохраняем данные формы, чтобы кнопка «Перезаписать» могла их применить.
                        session()->put("regatta_entry_overwrite:create:{$conflict->id}", [
                            'data' => $data,
                            'docs' => $requiredDocs,
                            'crew' => $crew,
                        ]);

                        Notification::make()
                            ->title('Заявка уже существует')
                            ->body('Эта команда уже подала заявку на эту регату. Можно перезаписать существующую заявку данными из формы.')
                            ->danger()
                            ->persistent()
                            ->actions([
                                Action::make('overwrite')
                                    ->label('Перезаписать')
                                    ->color('danger')
                                    ->button()
                                    ->close()
                                    ->dispatch('overwriteRegattaEntryOnCreate', ['conflictId' => $conflict->id]),
                            ])
                            ->send();

                        $this->halt();
                    }

                    /** @var RegattaEntry $record */
                    $record = $model::create($data);

                    app(SyncDocumentFilesAction::class)->execute($record, $requiredDocs);
                    RegattaEntryResource::syncCrew($record, $crew);

                    return $record;
                }),
        ];
    }
}
