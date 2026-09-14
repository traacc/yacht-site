<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\RegattaEntries\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\RegattaEntry\RecalculateEntryDocumentsCompleteAction;
use App\Enums\RegattaEntrySource;
use App\Filament\User\Resources\RegattaEntries\RegattaEntryResource;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\Team;
use App\Models\User;
use App\Models\Yacht;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ManageRegattaEntries extends ManageRecords
{
    protected static string $resource = RegattaEntryResource::class;

    /**
     * Возвращает список документов для заявки с флагом обязательности.
     *
     * Если передан $regattaId и у регаты настроены собственные документы
     * (entry_required_documents не null и не пустой) — возвращает их.
     * Иначе возвращает глобальные настройки (все считаются обязательными).
     *
     * @return array<int, array{doc_type: string, title: string, is_required: bool}>
     */
    public static function getRequiredDocuments(?string $regattaId = null): array
    {
        return app(RecalculateEntryDocumentsCompleteAction::class)->requiredDocuments($regattaId);
    }

    /**
     * Начальное состояние формы создания заявки на указанную регату.
     * Если пользователь управляет ровно одной командой — подставляет её, яхту по умолчанию и экипаж.
     *
     * @return array<string, mixed>|null null — регата не указана или недоступна для заявки
     */
    private function prefillForRegatta(mixed $regattaId): ?array
    {
        if (! is_string($regattaId)
            || ! Regatta::whereKey($regattaId)->where('date_end', '>=', now()->toDateString())->exists()) {
            return null;
        }

        /** @var User $user */
        $user = auth()->user();
        $teams = Team::manageableBy($user)->limit(2)->get(['id', 'default_yacht_id']);
        $team = $teams->count() === 1 ? $teams->first() : null;

        // Та же выборка, что и в опциях поля «Яхта»: яхта ещё не заявлена на эту регату.
        $yachtId = $team?->default_yacht_id && Yacht::whereKey($team->default_yacht_id)
            ->whereDoesntHave('regattaEntries', fn ($q) => $q->where('regatta_id', $regattaId))
            ->exists()
            ? $team->default_yacht_id
            : null;

        return [
            'regatta_id' => $regattaId,
            'team_id' => $team?->id,
            'yacht_id' => $yachtId,
            'crew' => RegattaEntryResource::buildCrewDefaults($team?->id, $regattaId),
            'required_documents' => array_map(
                fn (array $doc) => [
                    'doc_type' => $doc['doc_type'],
                    'title' => $doc['title'],
                    'is_required' => $doc['is_required'] ?? false,
                    'files' => [],
                ],
                self::getRequiredDocuments($regattaId),
            ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Подать заявку')
                // Переход с публичного сайта: ?action=create&actionArguments[regatta]=<id>
                ->mountUsing(function (Schema $schema, array $arguments): void {
                    $prefill = $this->prefillForRegatta($arguments['regatta'] ?? null);

                    $prefill === null ? $schema->fill() : $schema->fill($prefill);
                })
                ->using(function (array $data, string $model): Model {
                    $docs = $data['required_documents'] ?? [];
                    $crew = $data['crew'] ?? [];
                    unset($data['required_documents'], $data['crew']);

                    $data['status'] = 'pending';
                    $data['source'] = RegattaEntrySource::PersonalCabinet->value;

                    // Проверка дубликата до записи в БД
                    /** @var RegattaEntry $model */
                    $exists = $model::where('regatta_id', $data['regatta_id'])
                        ->where('team_id', $data['team_id'])
                        ->exists();

                    if ($exists) {
                        Notification::make()
                            ->title('Заявка уже существует')
                            ->body('Эта команда уже подала заявку на выбранную регату.')
                            ->danger()
                            ->send();

                        $this->halt();
                    }

                    /** @var RegattaEntry $record */
                    $record = $model::create($data);

                    // execute() попутно проставит documents_complete по факту сохранённых файлов.
                    app(SyncDocumentFilesAction::class)->execute($record, $docs);
                    RegattaEntryResource::syncCrew($record, $crew);

                    return $record;
                })
                ->createAnother(false)
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Готово!')
                        ->body('Ваша заявка успешно подана, ожидайте подтверждения'),
                ),
        ];
    }
}
