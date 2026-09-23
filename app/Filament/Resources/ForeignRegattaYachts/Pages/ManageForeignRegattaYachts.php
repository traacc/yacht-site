<?php

declare(strict_types=1);

namespace App\Filament\Resources\ForeignRegattaYachts\Pages;

use App\Filament\Resources\ForeignRegattaYachts\ForeignRegattaYachtResource;
use App\Models\ForeignRegattaYacht;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageForeignRegattaYachts extends ManageRecords
{
    protected static string $resource = ForeignRegattaYachtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Добавить яхту')
                // Цены можно заполнить позже, но про «лодка заведена, а на
                // витрине её не видно» лучше сказать сразу.
                ->after(fn (ForeignRegattaYacht $record) => ForeignRegattaYachtResource::warnIfNothingOffered($record)),
        ];
    }
}
