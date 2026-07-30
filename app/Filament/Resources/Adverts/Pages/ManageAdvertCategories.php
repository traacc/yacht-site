<?php

declare(strict_types=1);

namespace App\Filament\Resources\Adverts\Pages;

use App\Filament\Resources\Adverts\AdvertCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAdvertCategories extends ManageRecords
{
    protected static string $resource = AdvertCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Добавить категорию'),
        ];
    }
}
