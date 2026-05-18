<?php

namespace App\Filament\User\Resources\Teams\Pages;

use App\Actions\Team\CreateTeamAction;
use App\Filament\User\Resources\Teams\TeamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTeams extends ManageRecords
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Зарегистрировать команду')
                ->modalHeading('Зарегистрировать команду')
                ->using(fn (array $data) => app(CreateTeamAction::class)->handle(
                    $data,
                    auth()->user(),
                )),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        $data['approval_status'] = 'approved';

        return $data;
    }
}
