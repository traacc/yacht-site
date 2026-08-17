<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Filament\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Filament\Resources\RegattaEntries\RegattaEntryResource;
use App\Filament\Resources\RegattaResults\RelationManagers\EntriesRelationManager;
use App\Models\RegattaEntry;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

/**
 * Обработчики кнопки «Перезаписать» в уведомлении о дубликате заявки.
 *
 * Событие уведомления доходит до любого смонтированного Livewire-компонента
 * со слушателем, поэтому трейт подключается ко всем местам, откуда заявку можно
 * создать или отредактировать: без слушателя кнопка в уведомлении молча
 * ничего не делает.
 *
 * @see ManageRegattaEntries
 * @see EntriesRelationManager
 */
trait OverwritesRegattaEntries
{
    /**
     * Перезаписывает существующую заявку данными из формы и удаляет
     * редактируемую запись.
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
}
