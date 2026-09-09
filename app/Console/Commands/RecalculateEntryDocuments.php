<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\RegattaEntry\RecalculateEntryDocumentsCompleteAction;
use App\Models\Regatta;
use Illuminate\Console\Command;

class RecalculateEntryDocuments extends Command
{
    protected $signature = 'regatta-entries:recalculate-documents {regatta? : ID регаты. Если не указан — пересчитываются все}';

    protected $description = 'Пересчитывает флаг documents_complete у заявок на регату по актуальному списку обязательных документов.';

    public function handle(RecalculateEntryDocumentsCompleteAction $recalculate): int
    {
        $regattaId = $this->argument('regatta');

        if ($regattaId === null) {
            $changed = $recalculate->forAll();
            $this->info("Пересчитаны заявки всех регат. Флаг изменён у {$changed} шт.");

            return self::SUCCESS;
        }

        $regatta = Regatta::find($regattaId);

        if (! $regatta) {
            $this->error("Регата {$regattaId} не найдена.");

            return self::FAILURE;
        }

        $changed = $recalculate->forRegatta($regatta);
        $this->info("Пересчитаны заявки регаты «{$regatta->name}». Флаг изменён у {$changed} шт.");

        return self::SUCCESS;
    }
}
