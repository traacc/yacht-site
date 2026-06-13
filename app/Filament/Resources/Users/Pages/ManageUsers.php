<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->modalHeading('Новый пользователь')
                ->after(function (User $record, array $data): void {
                    $teamId = $data['crew_team_id'] ?? null;

                    if (blank($teamId)) {
                        return;
                    }

                    $added = UserResource::addToClosestRegattaCrew($record, $teamId);

                    Notification::make()
                        ->title($added
                            ? 'Пользователь добавлен в экипаж ближайшей регаты'
                            : 'Не удалось добавить в экипаж: команда не записана или пользователь в ней не состоит')
                        ->success()
                        ->send();
                }),
        ];
    }
}
