<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiNewsCandidates\Pages;

use App\Filament\Resources\AiNewsCandidates\AiNewsCandidateResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAiNewsCandidates extends ManageRecords
{
    protected static string $resource = AiNewsCandidateResource::class;
}
