<?php

namespace App\Filament\User\Resources\RegattaEntries\Pages;

use App\Filament\User\Resources\RegattaEntries\RegattaEntryResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\UniqueConstraintViolationException;

class ManageRegattaEntries extends ManageRecords
{
    protected static string $resource = RegattaEntryResource::class;

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
                })->createAnother(false),
        ];
    }
}
