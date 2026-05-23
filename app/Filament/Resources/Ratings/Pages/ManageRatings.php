<?php

namespace App\Filament\Resources\Ratings\Pages;

use App\Filament\Resources\Ratings\RatingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRatings extends ManageRecords
{
    protected static string $resource = RatingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
