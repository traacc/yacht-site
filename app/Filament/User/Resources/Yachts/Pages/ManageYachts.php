<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Yachts\Pages;

use App\Actions\Yacht\UpdateYachtRequiredDocumentsAction;
use App\Filament\User\Resources\Yachts\YachtResource;
use App\Models\Document;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
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
        foreach (static::getRequiredDocuments() as $doc) {
            $yacht->documents()->firstOrCreate(
                ['doc_type' => $doc['doc_type']],
                ['title'    => $doc['title']],
            );
        }
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
