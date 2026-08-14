<?php

declare(strict_types=1);

namespace App\Filament\Resources\ForeignRegattaYachts\Pages;

use App\Filament\Resources\ForeignRegattaYachts\ForeignRegattaYachtResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageForeignRegattaYachts extends ManageRecords
{
    protected static string $resource = ForeignRegattaYachtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Добавить яхту'),
        ];
    }
}
