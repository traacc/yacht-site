<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regatta_results', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('regatta_results', function (Blueprint $table) {
            $table->dropColumn('pdf_path');
        });
    }
};