<?php

declare(strict_types=1);

namespace App\Actions\RegattaEntry;

use App\Models\Document;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Пересчёт флага RegattaEntry::documents_complete.
 *
 * `documents_complete` — производная колонка: она кэширует ответ на вопрос
 * «по всем ли обязательным документам регаты у заявки есть хотя бы один файл».
 * Источник истины — записи Document заявки плюс актуальный список обязательных
 * документов, поэтому флаг обязан пересчитываться при любом изменении одной из
 * этих двух сторон:
 *
 *  — файлы заявки: см. SyncDocumentFilesAction::execute() и JoinRegattaModal;
 *  — настройки регаты: см. RegattaRequiredDocumentsObserver;
 *  — глобальные настройки: см. UpdateRegattaEntryRequiredDocumentsAction::save().
 *
 * Иначе флаг «залипает»: убранный из настроек документ навсегда оставляет
 * заявку помеченной как неполную.
 */
final class RecalculateEntryDocumentsCompleteAction
{
    /** Сколько заявок обрабатывать за один проход при массовом пересчёте. */
    private const CHUNK = 200;

    public function __construct(
        private readonly UpdateRegattaEntryRequiredDocumentsAction $globalSettings,
    ) {}

    /**
     * Список документов заявки для регаты с флагом обязательности.
     *
     * Если у регаты настроены собственные документы — применяются они,
     * иначе — глобальные настройки (в них все включённые документы обязательны).
     *
     * Единственная точка, где решается «чьи настройки применяются»; все Filament-страницы
     * и Livewire-формы обязаны обращаться сюда, а не собирать список самостоятельно.
     *
     * @return array<int, array{doc_type: string, title: string, is_required: bool}>
     */
    public function requiredDocuments(?string $regattaId = null): array
    {
        if ($regattaId !== null) {
            $regatta = Regatta::find($regattaId);

            if ($regatta && ! empty($regatta->entry_required_documents)) {
                return $regatta->getEntryDocuments();
            }
        }

        return $this->globalSettings->getRequiredList();
    }

    /**
     * Пересчитать и сохранить флаг для одной заявки.
     *
     * @return bool Новое значение documents_complete.
     */
    public function forEntry(RegattaEntry $entry): bool
    {
        $requiredTypes = $this->requiredTypes($entry->regatta_id);

        $complete = $this->isComplete($entry, $requiredTypes);
        $this->persist($entry, $complete);

        return $complete;
    }

    /**
     * Пересчитать флаг для всех заявок регаты.
     *
     * @return int Количество заявок, у которых флаг изменился.
     */
    public function forRegatta(Regatta|string $regatta): int
    {
        $regattaId = $regatta instanceof Regatta ? (string) $regatta->id : $regatta;

        return $this->recalculateQuery(
            RegattaEntry::query()->where('regatta_id', $regattaId),
            $this->requiredTypes($regattaId),
        );
    }

    /**
     * Пересчитать флаг для заявок всех регат, у которых нет собственного списка
     * документов, — то есть тех, на кого влияют глобальные настройки.
     *
     * @return int Количество заявок, у которых флаг изменился.
     */
    public function forGlobalDefaults(): int
    {
        $regattaIds = Regatta::query()
            ->where(fn ($q) => $q->whereNull('entry_required_documents')
                ->orWhereJsonLength('entry_required_documents', 0))
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        if ($regattaIds === []) {
            return 0;
        }

        return $this->recalculateQuery(
            RegattaEntry::query()->whereIn('regatta_id', $regattaIds),
            $this->requiredTypes(null),
        );
    }

    /**
     * Пересчитать флаг для всех заявок (разовый backfill).
     *
     * @return int Количество заявок, у которых флаг изменился.
     */
    public function forAll(): int
    {
        $changed = 0;

        foreach (Regatta::query()->pluck('id') as $regattaId) {
            $changed += $this->forRegatta((string) $regattaId);
        }

        return $changed;
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    /**
     * Ключи документов, обязательных к загрузке для заявок этой регаты.
     *
     * @return list<string>
     */
    private function requiredTypes(?string $regattaId): array
    {
        return array_values(array_filter(array_map(
            fn (array $doc): ?string => ($doc['is_required'] ?? false) ? ($doc['doc_type'] ?? null) : null,
            $this->requiredDocuments($regattaId),
        )));
    }

    /**
     * Есть ли у заявки хотя бы один файл по каждому обязательному типу.
     *
     * @param  list<string>  $requiredTypes
     */
    private function isComplete(RegattaEntry $entry, array $requiredTypes): bool
    {
        if ($requiredTypes === []) {
            return true;
        }

        /** @var Collection<int, Document> $documents */
        $documents = $entry->relationLoaded('documents')
            ? $entry->documents
            : $entry->documents()->get();

        $present = $documents
            ->filter(fn ($doc): bool => filled($doc->url))
            ->pluck('doc_type')
            ->unique()
            ->all();

        return array_diff($requiredTypes, $present) === [];
    }

    /**
     * Записать флаг напрямую запросом.
     *
     * Не через save(): переданная модель может быть устаревшей (флаг уже меняли
     * обсервером через другой инстанс), и Eloquent счёл бы атрибут «чистым» и
     * молча пропустил бы UPDATE. Обсерверы заявки на производную колонку не
     * реагируют, поэтому обходить их здесь безопасно.
     */
    private function persist(RegattaEntry $entry, bool $complete): void
    {
        $entry->newQueryWithoutRelationships()
            ->whereKey($entry->getKey())
            ->update(['documents_complete' => $complete]);

        $entry->setAttribute('documents_complete', $complete)
            ->syncOriginalAttribute('documents_complete');
    }

    /**
     * Пересчитать флаг для выборки заявок с общим списком обязательных документов.
     *
     * @param  Builder<RegattaEntry>  $query
     * @param  list<string>  $requiredTypes
     * @return int Количество заявок, у которых флаг изменился.
     */
    private function recalculateQuery($query, array $requiredTypes): int
    {
        $changed = 0;

        $query->with('documents:id,documentable_id,documentable_type,doc_type,url')
            ->chunkById(self::CHUNK, function (Collection $entries) use ($requiredTypes, &$changed): void {
                foreach ($entries as $entry) {
                    $complete = $this->isComplete($entry, $requiredTypes);

                    // Заявки здесь только что прочитаны из БД, сравнение достоверно.
                    if ($entry->documents_complete === $complete) {
                        continue;
                    }

                    $this->persist($entry, $complete);
                    $changed++;
                }
            });

        return $changed;
    }
}
