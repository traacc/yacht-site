<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regatta_entries', function (Blueprint $table) {
            // Признак полноты документов: false — заявка подана без всех
            // обязательных документов (помечается для рассмотрения организатором).
            $table->boolean('documents_complete')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('regatta_entries', function (Blueprint $table) {
            $table->dropColumn('documents_complete');
        });
    }
};
