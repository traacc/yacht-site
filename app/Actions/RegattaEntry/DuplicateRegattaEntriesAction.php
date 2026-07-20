<?php

declare(strict_types=1);

namespace App\Actions\RegattaEntry;

use App\Enums\RegattaEntrySource;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Дублирование заявок из одной регаты в другую.
 *
 * Копируются: команда, яхта (опционально), экипаж и — по запросу — документы заявки.
 * НЕ копируются: результаты гонок, записи реестра платежей, спец-пароль заявки
 * (entry_password хранится хэшем и повторному переносу не подлежит).
 *
 * Заявка команды, у которой в регате-приёмнике уже есть заявка, пропускается
 * (в БД действует уникальный индекс regatta_id + team_id).
 */
final class DuplicateRegattaEntriesAction
{
    /** Статусы заявок, копируемые по умолчанию. */
    public const DEFAULT_STATUSES = ['pending', 'approved'];

    /**
     * @param  string[]  $statuses  Статусы заявок-источников.
     * @param  string[]  $entryIds  Ограничить конкретными заявками (пусто — все подходящие).
     * @return Collection<int, array{entry: RegattaEntry, result: string, message: string|null}>
     *                                                                                           result: created | skipped | failed
     */
    public function execute(
        Regatta $source,
        Regatta $target,
        array $statuses = self::DEFAULT_STATUSES,
        array $entryIds = [],
        bool $withYacht = true,
        bool $withCrew = true,
        bool $withDocuments = false,
        bool $keepStatus = false,
        bool $dryRun = false,
    ): Collection {
        $entries = $this->sourceEntries($source, $statuses, $entryIds);

        $takenTeamIds = RegattaEntry::query()
            ->where('regatta_id', $target->id)
            ->pluck('team_id')
            ->all();

        return $entries->map(function (RegattaEntry $entry) use (
            $target, $takenTeamIds, $withYacht, $withCrew, $withDocuments, $keepStatus, $dryRun
        ) {
            if (in_array($entry->team_id, $takenTeamIds, true)) {
                return $this->row($entry, 'skipped', 'В регате-приёмнике уже есть заявка этой команды.');
            }

            if ($dryRun) {
                return $this->row($entry, 'created', null);
            }

            try {
                DB::transaction(fn () => $this->copy($entry, $target, $withYacht, $withCrew, $withDocuments, $keepStatus));
            } catch (ValidationException $e) {
                return $this->row($entry, 'failed', implode(' ', $e->validator->errors()->all()));
            } catch (Throwable $e) {
                return $this->row($entry, 'failed', $e->getMessage());
            }

            return $this->row($entry, 'created', null);
        });
    }

    /**
     * Заявки-источники, пригодные для копирования.
     *
     * @param  string[]  $statuses
     * @param  string[]  $entryIds
     * @return Collection<int, RegattaEntry>
     */
    public function sourceEntries(Regatta $source, array $statuses = self::DEFAULT_STATUSES, array $entryIds = []): Collection
    {
        return RegattaEntry::query()
            ->where('regatta_id', $source->id)
            ->when($statuses !== [], fn ($q) => $q->whereIn('status', $statuses))
            ->when($entryIds !== [], fn ($q) => $q->whereIn('id', $entryIds))
            ->with(['team', 'yacht', 'crew', 'documents'])
            ->get()
            ->sortBy(fn (RegattaEntry $entry) => $entry->team?->name ?? '')
            ->values();
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    private function copy(
        RegattaEntry $entry,
        Regatta $target,
        bool $withYacht,
        bool $withCrew,
        bool $withDocuments,
        bool $keepStatus,
    ): RegattaEntry {
        $status = $keepStatus ? $entry->status : 'pending';

        $copy = RegattaEntry::create([
            'regatta_id' => $target->id,
            'team_id' => $entry->team_id,
            'yacht_id' => $withYacht ? $entry->yacht_id : null,
            'status' => $status,
            'source' => RegattaEntrySource::Admin,
            'documents_complete' => $entry->documents_complete,
            // Оплата сбора всегда сбрасывается: сбор в новой регате не оплачен.
            'fee_paid' => false,
            'submitted_at' => $status === 'approved' ? now() : null,
        ]);

        if ($withCrew) {
            foreach ($entry->crew as $member) {
                $copy->crew()->create([
                    'team_member_id' => $member->team_member_id,
                    'role' => $member->role,
                ]);
            }
        }

        if ($withDocuments) {
            foreach ($entry->documents as $document) {
                $copy->documents()->create([
                    'doc_type' => $document->doc_type,
                    'title' => $document->title,
                    'url' => $this->copyFile($document->url),
                    'file_size_bytes' => $document->file_size_bytes,
                    'mime_type' => $document->mime_type,
                    'sort_order' => $document->sort_order,
                ]);
            }
        }

        return $copy;
    }

    /**
     * Скопировать файл документа в новый путь.
     *
     * Копия обязательна: удаление документа стирает файл с диска
     * (см. SyncDocumentFilesAction), поэтому две записи не должны делить один файл.
     */
    private function copyFile(?string $url): ?string
    {
        if (blank($url) || ! Storage::disk('public')->exists($url)) {
            return $url;
        }

        $extension = pathinfo($url, PATHINFO_EXTENSION);
        $target = trim(pathinfo($url, PATHINFO_DIRNAME), '.\/')
            .'/'.Str::uuid()->toString()
            .($extension !== '' ? '.'.$extension : '');

        Storage::disk('public')->copy($url, ltrim($target, '/'));

        return ltrim($target, '/');
    }

    /**
     * @return array{entry: RegattaEntry, result: string, message: string|null}
     */
    private function row(RegattaEntry $entry, string $result, ?string $message): array
    {
        return ['entry' => $entry, 'result' => $result, 'message' => $message];
    }
}
