<?php

use App\Models\Gallery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Человекопонятные адреса альбомов галереи.
 *
 * Раньше альбом открывался только через query-параметр с UUID
 * (/gallery?album=9c1f…), такую ссылку нельзя ни прочитать, ни проиндексировать.
 * Теперь у альбома есть slug и собственный адрес /gallery/{slug}.
 *
 * Колонка nullable: галерея создаётся в админке сразу черновиком с пустым
 * названием (см. ManageGalleries), slug появляется при первом сохранении
 * с названием — генерирует Gallery::generateSlug().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        // Бэкфилл существующих альбомов: без него старые галереи остались бы
        // без адреса. saveQuietly + timestamps=false, чтобы не сдвигать updated_at.
        Gallery::withTrashed()
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->with('season')
            ->orderBy('created_at')
            ->each(function (Gallery $gallery): void {
                $gallery->timestamps = false;
                $gallery->slug = Gallery::generateSlug($gallery->name, $gallery->slugYear(), $gallery->getKey());
                $gallery->saveQuietly();
            });
    }

    public function down(): void
    {
        Schema::table('gallery', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
