<?php

declare(strict_types=1);

namespace App\Filament\Resources\Votings\Pages;

use App\Filament\Resources\Votings\VotingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVotings extends ManageRecords
{
    protected static string $resource = VotingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Новое голосование')
                ->createAnother(false)
                ->mutateDataUsing(function (array $data): array {
                    $data['created_by'] ??= auth()->id();

                    return $data;
                }),
        ];
    }
}
