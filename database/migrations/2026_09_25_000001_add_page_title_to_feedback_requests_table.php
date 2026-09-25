<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_requests', function (Blueprint $table) {
            // Заголовок страницы (document.title), с которой отправлена форма.
            $table->string('page_title')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('feedback_requests', function (Blueprint $table) {
            $table->dropColumn('page_title');
        });
    }
};
