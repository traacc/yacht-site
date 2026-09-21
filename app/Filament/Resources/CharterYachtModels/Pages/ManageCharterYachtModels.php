<?php

declare(strict_types=1);

namespace App\Filament\Resources\CharterYachtModels\Pages;

use App\Filament\Resources\CharterYachtModels\CharterYachtModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCharterYachtModels extends ManageRecords
{
    protected static string $resource = CharterYachtModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Добавить модель'),
        ];
    }
}
