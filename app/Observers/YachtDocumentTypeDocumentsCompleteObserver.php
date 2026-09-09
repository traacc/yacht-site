<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\RegattaEntry\RecalculateEntryDocumentsCompleteAction;
use App\Models\YachtDocumentType;

/**
 * Третий источник изменений списка обязательных документов заявки — сам справочник
 * типов: удалённый тип выпадает из требований, новый настраиваемый тип с
 * is_default может в них попасть.
 *
 * Пересчитываем RegattaEntry::documents_complete целиком: типов единицы, правки
 * справочника редки, а «залипший» флаг стоит дороже лишнего прохода.
 */
final class YachtDocumentTypeDocumentsCompleteObserver
{
    public function __construct(
        private readonly RecalculateEntryDocumentsCompleteAction $recalculate,
    ) {}

    public function created(YachtDocumentType $type): void
    {
        $this->refresh();
    }

    public function updated(YachtDocumentType $type): void
    {
        if (! $type->wasChanged(['key', 'is_configurable'])) {
            return;
        }

        $this->refresh();
    }

    public function deleted(YachtDocumentType $type): void
    {
        $this->refresh();
    }

    /**
     * Кэш справочника сбрасывается хуком saved/deleted самой модели, а он срабатывает
     * позже событий created/updated/deleted — без явного сброса пересчёт прочитал бы
     * ещё старый список типов.
     */
    private function refresh(): void
    {
        YachtDocumentType::flushCache();

        $this->recalculate->forAll();
    }
}
