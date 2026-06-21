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
            $existing = $documentable->documents()->where('doc_type', $docType)->get();

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
     * Синхронизировать «плоский» набор файлов одного doc_type, где каждый файл —
     * самостоятельный Document с собственным title (= имя файла).
     *
     * В отличие от execute(), не группирует файлы под одним общим title:
     * подходит для «галерейной» загрузки пачкой (поле other_files в админке регаты).
     *
     * @param Model    $documentable
     * @param string   $docType
     * @param string[] $files       Список url-путей (state поля FileUpload)
     */
    public function executeFlat(Model $documentable, string $docType, array $files): void
    {
        $newFiles = array_values(array_filter((array) $files));

        /** @var \Illuminate\Database\Eloquent\Collection<int, Document> $existing */
        $existing     = $documentable->documents()->where('doc_type', $docType)->get();
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

        // Файлы к добавлению — каждый как отдельный документ, title = имя файла
        $toAdd     = array_diff($newFiles, $existingUrls);
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
                'title'           => basename($path),
                'url'             => $path,
                'file_size_bytes' => $size,
                'mime_type'       => $mime ?: null,
                'sort_order'      => $sortOrder,
            ]);
        }
    }

    /**
     * Загрузить «плоский» список url-файлов одного doc_type для FileUpload-state.
     *
     * @param Model  $documentable
     * @param string $docType
     *
     * @return string[]
     */
    public function loadFlat(Model $documentable, string $docType): array
    {
        return $documentable->documents()
            ->where('doc_type', $docType)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->pluck('url')
            ->filter(fn (?string $u) => $u !== null && $u !== '')
            ->values()
            ->toArray();
    }

    /**
     * Удалить все документы с doc_type, отсутствующими в текущем списке extra-документов.
     *
     * Вызывается для синхронизации «дополнительных» документов в админке:
     * если админ удалил весь блок extra-документа определённого типа, его записи тоже удаляются.
     *
     * @param Model    $documentable
     * @param string[] $excludeDocTypes  Типы обязательных документов (не трогать)
     * @param string[] $activeDocTypes   doc_type из текущего extra-repeater (оставить)
     */
    public function pruneOrphanedDocTypes(Model $documentable, array $excludeDocTypes, array $activeDocTypes): void
    {
        $orphaned = $documentable->documents()
            ->whereNotIn('doc_type', array_merge($excludeDocTypes, $activeDocTypes))
            ->get();

        foreach ($orphaned as $doc) {
            if ($doc->url !== '' && $doc->url !== null && Storage::disk('public')->exists($doc->url)) {
                Storage::disk('public')->delete($doc->url);
            }
            $doc->delete();
        }
    }

    /**
     * Загрузить существующие документы в формат Repeater-state.
     *
     * @param Model                                              $documentable
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

    /**
     * Загрузить «дополнительные» документы (не входящие в список обязательных).
     *
     * Группирует по doc_type, для каждого типа собирает все url-файлы.
     *
     * @param Model    $documentable
     * @param string[] $requiredDocTypes  Ключи обязательных типов (исключить из выборки)
     *
     * @return array<int, array{doc_type: string, title: string, files: string[]}>
     */
    public function loadExtra(Model $documentable, array $requiredDocTypes): array
    {
        $query = $documentable->documents()
            ->orderBy('sort_order')
            ->orderBy('created_at');

        if ($requiredDocTypes !== []) {
            $query->whereNotIn('doc_type', $requiredDocTypes);
        }

        $grouped = $query->get()->groupBy('doc_type');

        $result = [];
        foreach ($grouped as $docType => $docs) {
            $title = $docs->first()?->title ?? '';
            $files = $docs->pluck('url')->filter(fn (string $u) => $u !== '')->values()->toArray();

            $result[] = [
                'doc_type' => $docType,
                'title'    => $title,
                'files'    => $files,
            ];
        }

        return $result;
    }
}
