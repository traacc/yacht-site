<?php

declare(strict_types=1);

namespace App\Filament\Resources\Regattas\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\Regatta\ReplicateRegattaAction;
use App\Actions\Regatta\UpdateRegattaRequiredDocumentsAction;
use App\Filament\Resources\Regattas\RegattaResource;
use App\Exports\RegattaParticipantsExport;
use App\Models\Regatta;
use App\Models\Series;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageRegattas extends ManageRecords
{
    protected static string $resource = RegattaResource::class;

    /**
     * Возвращает динамический список обязательных документов из настроек.
     *
     * @return array<int, array{doc_type: string, title: string}>
     */
    public static function getRequiredDocuments(): array
    {
        return app(UpdateRegattaRequiredDocumentsAction::class)->getRequiredList();
    }

    /**
     * Формирует название регаты-этапа серии: «<название> — Этап N».
     */
    protected static function seriesStageName(string $baseName, int $position): string
    {
        $baseName = trim($baseName);
        $baseName = $baseName === '' ? 'Регата' : $baseName;

        return "{$baseName} — Этап {$position}";
    }

    /**
     * Сдвигает расписание регаты на разницу дат старта относительно первого этапа,
     * чтобы скопированные мероприятия попадали на даты своего этапа.
     */
    protected static function shiftScheduleEvents(Regatta $regatta, ?Carbon $stageOneDay, ?string $stageStart): void
    {
        if ($stageOneDay === null || blank($stageStart)) {
            return;
        }

        $dayOffset = (int) $stageOneDay->diffInDays(Carbon::parse($stageStart)->startOfDay(), false);
        if ($dayOffset === 0) {
            return;
        }

        foreach ($regatta->scheduleEvents()->get() as $event) {
            if ($event->event_datetime === null) {
                continue;
            }

            $event->event_datetime = $event->event_datetime->copy()->addDays($dayOffset);
            $event->save();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('documentSettings')
                ->label('Документы')
                ->icon('heroicon-o-document-text')
                ->color('white')
                ->url(fn () => \App\Filament\Resources\RegattaEntryDocumentTypeResource::getUrl()),
            Action::make('exportParticipantsXlsx')
                ->label('Экспорт участников (.xlsx)')
                ->icon('heroicon-o-table-cells')
                ->color('white')
                ->form([
                    Select::make('regatta_id')
                        ->label('Регата')
                        ->options(fn (): array => Regatta::exportSelectOptions())
                        ->searchable()
                        ->required(),
                ])
                ->modalHeading('Экспорт участников в .xlsx')
                ->modalDescription('Список участников регаты (парусный №, яхта, команда, экипаж).')
                ->modalSubmitActionLabel('Скачать .xlsx')
                ->action(function (array $data) {
                    $regatta = Regatta::findOrFail($data['regatta_id']);
                    $export  = app(RegattaParticipantsExport::class);

                    if ($export->loadParticipants($regatta)->isEmpty()) {
                        Notification::make()
                            ->title('У регаты нет заявок для экспорта')
                            ->warning()
                            ->send();

                        return null;
                    }

                    return $export->download($regatta);
                }),
            CreateAction::make()->modalHeading('Новая регата')
                ->createAnother(false)
                ->using(function (array $data, string $model): Regatta {
                    // Режим серии: даты этапов задаются в отдельном списке,
                    // первая регата (Этап 1) создаётся здесь, остальные — в after().
                    $seriesMode = (bool) ($data['create_as_series'] ?? false);
                    $seriesName = $data['series_name'] ?? null;
                    $stages     = $data['series_stages'] ?? [];
                    unset($data['create_as_series'], $data['series_name'], $data['series_stages']);

                    $requiredDocs = $data['required_documents'] ?? [];
                    $extraDocs    = $data['extra_documents'] ?? [];
                    $otherFiles   = $data['other_files'] ?? [];
                    $data['entry_required_documents'] = RegattaResource::assembleEntryRequiredDocuments(
                        $data['entry_doc_selected'] ?? [],
                        $data['entry_doc_required'] ?? [],
                    );
                    unset($data['required_documents'], $data['extra_documents'], $data['other_files'], $data['entry_doc_selected'], $data['entry_doc_required']);

                    if ($seriesMode && $stages !== []) {
                        $series = Series::create([
                            'season_id' => $data['season_id'] ?? null,
                            'name'      => $seriesName,
                        ]);

                        $firstStage         = $stages[0];
                        $data['series_id']  = $series->id;
                        $data['name']       = self::seriesStageName($data['name'] ?? '', 1);
                        $data['date_start'] = $firstStage['date_start'] ?? null;
                        $data['date_end']   = $firstStage['date_end'] ?? null;
                        $data['time_start'] = $firstStage['time_start'] ?? null;
                        $data['time_end']   = $firstStage['time_end'] ?? null;
                    }

                    /** @var Regatta $record */
                    $record = $model::create($data);

                    $sync = app(SyncDocumentFilesAction::class);
                    $sync->execute($record, $requiredDocs);
                    $sync->execute($record, $extraDocs);
                    $sync->executeFlat($record, 'other', $otherFiles);

                    return $record;
                })
                ->after(function (Regatta $record, array $data): void {
                    if (empty($data['create_as_series'])) {
                        return;
                    }

                    $stages = $data['series_stages'] ?? [];
                    if (count($stages) < 2) {
                        return;
                    }

                    // Первый этап уже создан в using() (в него сохранены расписание
                    // и документы). Остальные этапы клонируем с него и сдвигаем
                    // расписание на разницу дат старта.
                    $baseName    = $data['name'] ?? '';
                    $stageOneDay = $record->date_start ? $record->date_start->copy()->startOfDay() : null;
                    $replicate   = app(ReplicateRegattaAction::class);

                    foreach (array_slice($stages, 1) as $offset => $stage) {
                        $replica = $replicate->execute($record, [
                            'name'       => self::seriesStageName($baseName, $offset + 2),
                            'date_start' => $stage['date_start'] ?? null,
                            'date_end'   => $stage['date_end'] ?? null,
                            'time_start' => $stage['time_start'] ?? null,
                            'time_end'   => $stage['time_end'] ?? null,
                        ]);

                        self::shiftScheduleEvents($replica, $stageOneDay, $stage['date_start'] ?? null);
                    }
                }),
        ];
    }
}
