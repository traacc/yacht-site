<?php

namespace App\Filament\Resources\FeedbackRequests\Pages;

use App\Filament\Resources\FeedbackRequests\FeedbackRequestsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFeedbackRequests extends ManageRecords
{
    protected static string $resource = FeedbackRequestsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
