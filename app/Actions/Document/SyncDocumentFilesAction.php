<?php

declare(strict_types=1);

namespace App\Actions\Document;

use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Синхронизация множественных файлов документов для documentable-сущности.
 *
 * Принимает массив элементов вида:
 *   ['doc_type' => string, 'title' => string, 'files' => string[]]
 *
 * Для каждого doc_type:
 *  — удаляет Document-записи (и физические файлы), которых нет в новом списке;
 *  — создаёт новые Document-записи для добавленных файлов;
 *  — оставляет без изменений уже существующие.
 */
final class SyncDocumentFilesAction
{
    /**
     * Синхронизировать документы для сущности.
     *
     * @param Model                                                              $documentable Яхта / Заявка
     * @param array<int, array{doc_type: string, title: string, files: string[]}> $documentsData
     */
    public function execute(Model $documentable, array $documentsData): void
    {
        foreach ($documentsData as $item) {
            $docType  = $item['doc_type'] ?? '';
            $title    = $item['title'] ?? '';
            $newFiles = array_values(array_filter((array) ($item['files'] ?? [])));

            /** @var \Illuminate\Database\Eloquent\Collection<int, Document> $existing */
            $existing    = $documentable->documents()->where('doc_type', $docType)->get();
            $existingMap = $existing->keyBy('url');

            $existingUrls = $existing->pluck('url')->filter()->values()->toArray();

            // Файлы к удалению
            $toRemove = array_diff($existingUrls, $newFiles);
            if ($toRemove !== []) {
                $documentable->documents()
                    ->where('doc_type', $docType)
                    ->whereIn('url', $toRemove)
                    ->delete();

                foreach ($toRemove as $path) {
                    if ($path !== '' && Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }

            // Файлы к добавлению
            $toAdd = array_diff($newFiles, $existingUrls);
            $sortOrder = $existing->max('sort_order') ?? 0;

            foreach ($toAdd as $path) {
                if ($path === '') {
                    continue;
                }

                $sortOrder++;

                $size = null;
                $mime = null;

                if (Storage::disk('public')->exists($path)) {
                    $size = Storage::disk('public')->size($path);
                    $mime = Storage::disk('public')->mimeType($path);
                }

                $documentable->documents()->create([
                    'doc_type'        => $docType,
                    'title'           => $title,
                    'url'             => $path,
                    'file_size_bytes' => $size,
                    'mime_type'       => $mime ?: null,
                    'sort_order'      => $sortOrder,
                ]);
            }
        }
    }

    /**
     * Загрузить существующие документы в формат Repeater-state.
     *
     * @param Model                                            $documentable
     * @param array<int, array{doc_type: string, title: string}> $requiredDocs
     *
     * @return array<int, array{doc_type: string, title: string, files: string[]}>
     */
    public function load(Model $documentable, array $requiredDocs): array
    {
        /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Document>> $grouped */
        $grouped = $documentable->documents()
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->groupBy('doc_type');

        return array_map(function (array $doc) use ($grouped) {
            $docType = $doc['doc_type'];

            /** @var \Illuminate\Support\Collection<int, Document>|null $docs */
            $docs = $grouped->get($docType);

            $files = $docs
                ? $docs->pluck('url')->filter(fn (string $u) => $u !== '')->values()->toArray()
                : [];

            return [
                'doc_type' => $docType,
                'title'    => $doc['title'],
                'files'    => $files,
            ];
        }, $requiredDocs);
    }
}
