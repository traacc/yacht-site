<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('regatta_results', function (Blueprint $table) {
            // Опубликован ли результат
            $table->boolean('is_published')->default(false)->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('regatta_results', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });
    }
};
