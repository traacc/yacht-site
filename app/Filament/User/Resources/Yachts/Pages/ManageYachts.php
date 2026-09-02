<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Yachts\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\Yacht\SyncYachtOptionsAction;
use App\Actions\Yacht\UpdateYachtRequiredDocumentsAction;
use App\Filament\User\Resources\OwnershipTransfers\OwnershipTransferResource;
use App\Filament\User\Resources\Yachts\YachtResource;
use App\Mail\YachtRegistered;
use App\Models\Scopes\OwnedScope;
use App\Models\Yacht;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Mail;

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
                    $orcFiles = $data['orc_cert_file'] ?? [];
                    $rentals = $data['rentals'] ?? [];
                    $optionSync = app(SyncYachtOptionsAction::class);
                    $optionSelections = $optionSync->extract($data);
                    $selectedYachtId = $data['selected_yacht_id'] ?? null;
                    unset($data['required_documents'], $data['orc_cert_file'], $data['rentals'], $data['yacht_search'], $data['selected_yacht_id']);

                    // Если яхта выбрана из базы — присваиваем её.
                    // Иначе ищем свободную яхту (без user_id) по номеру ВФПС
                    // и перезаписываем её; при отсутствии — создаём новую.
                    $existing = null;

                    if ($selectedYachtId) {
                        $existing = Yacht::query()
                            ->withoutGlobalScope(OwnedScope::class)
                            ->findOrFail($selectedYachtId);
                    } elseif (! empty($data['vfps_number'])) {
                        $existing = Yacht::query()
                            ->withoutGlobalScope(OwnedScope::class)
                            ->withTrashed()
                            ->whereNull('user_id')
                            ->where('vfps_number', $data['vfps_number'])
                            ->first();
                    }

                    if ($existing) {
                        /** @var Yacht $record */
                        $record = $existing;

                        if ($record->trashed()) {
                            $record->restore();
                        }

                        $record->update([
                            ...$data,
                            'user_id' => auth()->id(),
                            'approval_status' => 'approved',
                        ]);
                    } else {
                        $data['user_id'] = auth()->id();
                        $data['approval_status'] = 'approved';

                        /** @var Yacht $record */
                        $record = $model::create($data);
                    }

                    app(SyncDocumentFilesAction::class)->execute($record, $docs);
                    app(SyncDocumentFilesAction::class)->executeFlat($record, Yacht::ORC_DOC_TYPE, $orcFiles);

                    YachtResource::syncRentals($record, $rentals);
                    $optionSync->execute($record, $optionSelections);

                    // Уведомляем администраторов о регистрации новой яхты пользователем.
                    $adminEmails = app(SettingsService::class)->adminNotificationEmails();
                    if ($adminEmails !== []) {
                        try {
                            Mail::to($adminEmails)
                                ->send(new YachtRegistered($record));
                        } catch (\Exception $e) {
                            report($e);
                        }
                    }

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

            Action::make('requestTransfer')
                ->label('Запросить передачу яхты')
                ->icon('heroicon-o-arrows-right-left')
                ->color('white')
                ->modalHeading('Запросить передачу яхты')
                ->modalSubmitActionLabel('Отправить заявку')
                ->schema(OwnershipTransferResource::formComponents())
                ->action(function (array $data): void {
                    OwnershipTransferResource::createTransfer($data);

                    Notification::make()
                        ->success()
                        ->title('Заявка отправлена')
                        ->body('Администратор рассмотрит вашу заявку на передачу яхты.')
                        ->send();
                }),
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
