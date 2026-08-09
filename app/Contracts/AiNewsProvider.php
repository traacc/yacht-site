<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Ai\Data\AiNewsBatch;
use App\Services\Ai\Data\AiNewsRequest;

interface AiNewsProvider
{
    public function isConfigured(): bool;

    public function model(): string;

    public function discover(AiNewsRequest $request): AiNewsBatch;
}
