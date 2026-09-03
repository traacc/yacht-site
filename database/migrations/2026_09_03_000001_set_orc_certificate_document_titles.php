<?php

declare(strict_types=1);

use App\Models\Yacht;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Привести заголовки уже загруженных ORC-сертификатов к общему виду.
     *
     * До появления отдельного поля загрузки title писался как имя файла,
     * а FileUpload сохранял файл под случайным uuid — в карточке яхты
     * документ выглядел как «01k5x….pdf».
     */
    public function up(): void
    {
        DB::table('documents')
            ->where('doc_type', Yacht::ORC_DOC_TYPE)
            ->where('title', '!=', Yacht::ORC_DOC_TITLE)
            ->update([
                'title' => Yacht::ORC_DOC_TITLE,
                'updated_at' => now(),
            ]);
    }

    /**
     * Необратима: прежние заголовки (имена файлов) не сохранялись отдельно.
     */
    public function down(): void {}
};
