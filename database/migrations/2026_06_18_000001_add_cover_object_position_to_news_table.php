<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            // CSS object-position для обложки в карточках (например: center, top, bottom)
            $table->string('cover_object_position')->default('center')->after('cover_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn('cover_object_position');
        });
    }
};
