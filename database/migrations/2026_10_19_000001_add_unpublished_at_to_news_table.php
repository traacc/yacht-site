<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Снятие новости с публикации.
 *
 * Отдельная колонка, а не обнуление published_at: при возврате на сайт
 * новость сохраняет исходную дату и место в ленте, а «снята» не путается
 * с «запланирована на будущее».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table): void {
            $table->timestamp('unpublished_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table): void {
            $table->dropColumn('unpublished_at');
        });
    }
};
