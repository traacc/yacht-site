<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Yachts\Pages;

use App\Filament\User\Resources\Yachts\YachtResource;
use App\Models\Document;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageYachts extends ManageRecords
{
    protected static string $resource = YachtResource::class;

    /** Фиксированный список обязательных документов яхты */
    public const REQUIRED_DOCUMENTS = [
        ['doc_type' => 'orc_certificate', 'title' => 'ORC-сертификат'],
        ['doc_type' => 'ship_ticket',     'title' => 'Судовой билет'],
        ['doc_type' => 'insurance',       'title' => 'Страховка'],
    ];

    /** Метки для отображения в сообщениях об ошибках */
    private const DOCUMENT_LABELS = [
        'orc_certificate' => 'ORC-сертификат',
        'ship_ticket'     => 'Судовой билет',
        'insurance'       => 'Страховка',
    ];

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Зарегистрировать яхту')
                ->modalHeading('Зарегистрировать яхту')
                ->before(function (array $data, CreateAction $action): void {
                    $missing = self::getMissingDocuments($data['documents'] ?? []);

                    if ($missing !== []) {
                        Notification::make()
                            ->title('Не загружены обязательные документы')
                            ->body('Загрузите следующие документы: ' . implode(', ', $missing) . '.')
                            ->danger()
                            ->persistent()
                            ->send();

                        $action->halt();
                    }
                })
                ->mutateFormDataUsing(function (array $data): array {
                    $data['user_id'] = auth()->id();
                    $data['approval_status'] = 'approved';

                    return $data;
                })
                ->after(function (): void {
                    $this->mountAction('showInfoModal');
                }),
        ];
    }

    /** Создаёт недостающие обязательные документы для яхты */
    public static function ensureRequiredDocuments(\App\Models\Yacht $yacht): void
    {
        foreach (self::REQUIRED_DOCUMENTS as $doc) {
            $yacht->documents()->firstOrCreate(
                ['doc_type' => $doc['doc_type']],
                ['title'    => $doc['title']],
            );
        }
    }

    /**
     * Возвращает список меток документов, у которых не заполнен url.
     *
     * @param  array<int, array<string, mixed>>  $documents
     * @return list<string>
     */
    private static function getMissingDocuments(array $documents): array
    {
        $missing = [];

        foreach (self::REQUIRED_DOCUMENTS as $required) {
            $docType = $required['doc_type'];

            $uploaded = collect($documents)->first(
                fn (array $doc): bool => ($doc['doc_type'] ?? '') === $docType
            );

            if ($uploaded === null || empty($uploaded['url'])) {
                $missing[] = self::DOCUMENT_LABELS[$docType];
            }
        }

        return $missing;
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
