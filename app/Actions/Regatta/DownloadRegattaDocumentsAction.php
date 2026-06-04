<?php

namespace App\Actions\Regatta;

use App\Models\Regatta;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

final class DownloadRegattaDocumentsAction
{
    /**
     * Создать ZIP-архив со всеми документами регаты и вернуть ответ для скачивания.
     */
    public function execute(Regatta $regatta): BinaryFileResponse
    {
        $documents = $regatta->documents()
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->get();

        if ($documents->isEmpty()) {
            abort(404, 'Нет документов для скачивания');
        }

        $zip = new ZipArchive;
        $zipFilename = tempnam(sys_get_temp_dir(), 'regatta_docs_') . '.zip';

        if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Не удалось создать архив');
        }

        $filesAdded = 0;

        foreach ($documents as $doc) {
            $filePath = Storage::disk('public')->path($doc->url);

            if (!file_exists($filePath)) {
                continue;
            }

            $typeDir = $doc->doc_type ?? 'other';
            $originalName = basename($doc->url);

            $zip->addFile($filePath, $typeDir . '/' . $originalName);
            $filesAdded++;
        }

        $zip->close();

        if ($filesAdded === 0) {
            unlink($zipFilename);
            abort(404, 'Файлы документов не найдены на сервере');
        }

        $safeName = preg_replace('/[^\w\s\-а-яё]/ui', '', $regatta->name);
        $safeName = trim(preg_replace('/\s+/', '_', $safeName)) ?: 'regatta';

        return response()
            ->download($zipFilename, "{$safeName}_documents.zip")
            ->deleteFileAfterSend(true);
    }
}