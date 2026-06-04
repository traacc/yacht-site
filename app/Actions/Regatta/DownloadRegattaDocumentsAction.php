<?php

namespace App\Actions\Regatta;

use App\Models\Regatta;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

final class DownloadRegattaDocumentsAction
{
    /**
     * Создать ZIP-архив со всеми документами регаты и вернуть ответ для скачивания.
     */
    public function execute(Regatta $regatta): Response
    {
        $documents = $regatta->documents()->whereNotNull('url')->where('url', '!=', '')->get();

        $zip = new ZipArchive;
        $zipFilename = tempnam(sys_get_temp_dir(), 'regatta_docs_') . '.zip';

        if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Не удалось создать архив');
        }

        foreach ($documents as $doc) {
            $filePath = Storage::disk('public')->path($doc->url);

            if (!file_exists($filePath)) {
                continue;
            }

            // Формируем читаемое имя файла в архиве: тип_документа/название.pdf
            $typeDir = $doc->doc_type ?? 'other';
            $originalName = basename($doc->url);

            $zip->addFile($filePath, $typeDir . '/' . $originalName);
        }

        $zip->close();

        $safeName = preg_replace('/[^\w\s\-а-яё]/ui', '', $regatta->name);
        $safeName = trim(preg_replace('/\s+/', '_', $safeName)) ?: 'regatta';

        return response()
            ->download($zipFilename, "{$safeName}_documents.zip")
            ->deleteFileAfterSend(true);
    }
}