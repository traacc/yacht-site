<?php

declare(strict_types=1);

namespace App\Filament\Resources\RegattaEntries\Pages;

use App\Actions\RegattaEntry\UpdateRegattaEntryRequiredDocumentsAction;
use App\Filament\Concerns\OverwritesRegattaEntries;
use App\Filament\Resources\RegattaEntries\RegattaEntryResource;
use App\Filament\Resources\RegattaEntryDocumentTypeResource;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Services\RgdParticipantsExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageRegattaEntries extends ManageRecords
{
    use OverwritesRegattaEntries;

    protected static string $resource = RegattaEntryResource::class;

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
                ->url(fn () => RegattaEntryDocumentTypeResource::getUrl()),
            Action::make('exportParticipants')
                ->label('Экспорт участников (.rgd)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('white')
                ->form([
                    Select::make('regatta_id')
                        ->label('Регата')
                        ->options(fn (): array => Regatta::exportSelectOptions())
                        ->searchable()
                        ->required(),
                ])
                ->modalHeading('Экспорт участников в .rgd')
                ->modalDescription('Файл зачётной группы «КАРТЕР 30» только с данными участников (Windows-1251).')
                ->modalSubmitActionLabel('Скачать .rgd')
                ->action(function (array $data) {
                    // Список в Select уже ограничен, но присланный id управляем клиентом.
                    $regatta = Regatta::query()
                        ->visibleForUser()
                        ->whereKey($data['regatta_id'])
                        ->firstOrFail();
                    $exporter = app(RgdParticipantsExporter::class);
                    $entries = $exporter->loadParticipants($regatta);

                    if ($entries->isEmpty()) {
                        Notification::make()
                            ->title('У регаты нет заявок для экспорта')
                            ->warning()
                            ->send();

                        return null;
                    }

                    $bytes = $exporter->toBytes($exporter->build($regatta, $entries));

                    return response()->streamDownload(
                        function () use ($bytes): void {
                            echo $bytes;
                        },
                        $exporter->filename($regatta),
                        ['Content-Type' => 'application/octet-stream'],
                    );
                }),
            CreateAction::make()
                ->modalHeading('Новая заявка на регату')
                ->createAnother(false)
                ->using(fn (array $data, Action $action): RegattaEntry => RegattaEntryResource::createFromFormData($data, $action)),
        ];
    }
}
