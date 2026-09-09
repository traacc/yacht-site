<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\RegattaEntry\RecalculateEntryDocumentsCompleteAction;
use App\Models\Regatta;

/**
 * Держит RegattaEntry::documents_complete в согласии со списком обязательных
 * документов регаты.
 *
 * Без этого убранный из настроек документ навсегда оставлял бы уже поданные
 * заявки помеченными как неполные: флаг вычисляется только в момент сохранения
 * файлов и сам по себе не устаревает.
 */
final class RegattaRequiredDocumentsObserver
{
    public function __construct(
        private readonly RecalculateEntryDocumentsCompleteAction $recalculate,
    ) {}

    public function updated(Regatta $regatta): void
    {
        if (! $regatta->wasChanged('entry_required_documents')) {
            return;
        }

        $this->recalculate->forRegatta($regatta);
    }
}
