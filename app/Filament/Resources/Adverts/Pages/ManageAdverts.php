<?php

declare(strict_types=1);

namespace App\Filament\Resources\Adverts\Pages;

use App\Filament\Resources\Adverts\AdvertCategoryResource;
use App\Filament\Resources\Adverts\AdvertResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageAdverts extends ManageRecords
{
    protected static string $resource = AdvertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Категории в меню не показываются — вход к ним отсюда.
            Action::make('categories')
                ->label('Категории')
                ->icon('heroicon-o-tag')
                ->color('gray')
                ->url(AdvertCategoryResource::getUrl(panel: 'admin')),
        ];
    }
}
